<?php

namespace App\Jobs;

use App\Helpers\_CommonCache;
use App\Helpers\_Obet33;
use App\Helpers\_QB838;
use App\Helpers\_WBet;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Modules\_WBetController;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DateTimeZone;


class ProcessWbetBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $argument;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($argument)
    {
        $this->argument = $argument;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $key = $this->argument;
        if ($key) {
            $betTickets = _WBet::getBets($key, true);
        } else {
            $betTickets = _WBet::getBets();
        }
        $this->process($betTickets);
    }


    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            $ids = [];
            foreach ($betTickets as $betTicket) {

                $winlose = $betTicket['payout'] - $betTicket['bet'];
                $starttime = Carbon::parse($betTicket['start_time'], 'UTC')->setTimezone('Asia/Kuala_Lumpur');
                $endtime = Carbon::parse($betTicket['end_time'], 'UTC')->setTimezone('Asia/Kuala_Lumpur');
                $accountdate = $endtime;

                //for ongoing sport bet
                if ($betTicket['status'] === 0) {
                    $winlose = 0;
                    // $accountdate = $starttime;
                }

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                switch ($betTicket['status']) {
                    case 1:
                        $bet_status = "SETTLED";
                        break;
                    case 0:
                        $bet_status = "RUNNING";
                        break;
                    case -1:
                        $bet_status = "CANCELLED";
                        break;
                    default:
                        $bet_status = "UNKNOWN";
                        break;
                }

                $valid_stake = $betTicket['bet'];
                if ($winlose > 0) {
                    if ($winlose >= $betTicket['bet']) {
                        $valid_stake = $betTicket['bet'];
                    } else {
                        $valid_stake = $winlose;
                    }
                } else if ($winlose < 0) {
                    $valid_stake = abs($winlose);
                }

                $product = _CommonCache::product_code('WBET');
                $member_account = MemberAccount::where('account', $betTicket['member'])
                    ->where('product_id', $product->id)->first();

                $betDetail = [
                    'bet_id' => 'WBET_' . $betTicket['id'],
                    'product' => "WBET",
                    'game' => $betTicket['game_id'],
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => $member_account->username ?? $betTicket['member'],
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $valid_stake,
                    'payout' => $betTicket['payout'],
                    'winlose' => $winlose,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => $accountdate,
                    'round_at' => $starttime,
                    'round_date' => $starttime->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];

                $ids[] = $betTicket['id'];
                $upserts[] = $betDetail;
            }

            $ticket = '';
            foreach ($ids as $item) {
                $ticket .= $item . ', ';
            }
            $ticket = rtrim($ticket, ', ');
            if ($ticket != '') {
                $response = _WBetController::init("markbyjson.aspx", [
                    "operatorcode" => config('api.WBET_OPERATOR_CODE'),
                    'ticket' => $ticket,
                    'providercode' => config('api.WBET_PROVIDER_CODE'),
                ]);
            }


            BetLog::upsertByChunk($upserts);
        }
    }
}
