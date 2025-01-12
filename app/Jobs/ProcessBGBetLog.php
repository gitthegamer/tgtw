<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Evo888;
use App\Helpers\_PGS;
use App\Helpers\_Vpower;
use App\Helpers\BG;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DateTimeZone;


class ProcessBGBetLog implements ShouldQueue
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
        $this->queue = 'fetch_bet_logs';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $date = $this->argument ? Carbon::parse($this->argument) : now();
        $startDate = $date->copy()->subHours(12)->subDay()->format('Y-m-d H:i:s');
        $endDate = $date->copy()->subHours(12)->format('Y-m-d H:i:s');

        $betTickets = BG::getBets($startDate, $endDate);
        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['payment'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['payment'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                switch ($betTicket['orderStatus']) {
                    case 0:
                        $bet_status = "BET NOT EXISTS";
                        break;
                    case 1:
                        $bet_status = "NOT SETTLED";
                        break;
                    case 2:
                        $bet_status = "SETTLED";
                        break;
                    case 3:
                        $bet_status = "SETTLED";
                        break;
                    case 4:
                        $bet_status = "SETTLED";
                        break;
                    case 5:
                        $bet_status = "CANCEL";
                        break;
                    case 6:
                        $bet_status = "EXPIRES";
                        break;
                    case 7:
                        $bet_status = "SYSTEM CANCEL";
                        break;
                    default:
                        $bet_status = "UNKNOWN";
                        break;
                }

                $accountDate = new DateTime($betTicket['lastUpdateTime'], new DateTimeZone('UTC'));
                $roundAt =  new DateTime($betTicket['orderTime'], new DateTimeZone('UTC'));
                $accountDate->setTimezone(new DateTimeZone('GMT+12'));
                $roundAt->setTimezone(new DateTimeZone('GMT+12'));
                
                $betDetail = [
                    'bet_id' => "BG_" . $betTicket['orderId'],
                    'product' => "BG",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['loginId'],
                    'stake' => abs($betTicket['bAmount']), //correct
                    'valid_stake' => abs($betTicket['bAmount']), //correct
                    'payout' => $betTicket['aAmount'], //
                    'winlose' => $betTicket['payment'], //correct
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => $accountDate->format('Y-m-d H:i:s'),
                    'round_at' => $roundAt->format('Y-m-d H:i:s'),
                    'round_date' => $roundAt->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];
                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);

            foreach ($betTickets as $betTicket) {
                ProcessBGBetDetail::dispatch($betTicket['orderId']);
            }
        }
    }
}
