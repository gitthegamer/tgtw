<?php

namespace App\Jobs;


use App\Helpers\_M8;
use App\Helpers\_PGS;
use App\Helpers\_Vpower;
use App\Helpers\IBC;
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


class ProcessM8BetLog implements ShouldQueue
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

        $betTickets = _M8::getBets();
        $this->process($betTickets);

    }


    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            $ticketIds = [];
            foreach ($betTickets as $betTicket) {
                $payout_status = "WAITING";
                $bet_status = "WAITING";
                switch ($betTicket['res']) {
                    case 'WA':
                        $payout_status = "WINALL";
                        $bet_status = "SETTLED";
                        break;
                    case 'P':
                        $payout_status = "NOTMATCHOVER";
                        $bet_status = "NOTMATCHOVER";
                        break;
                    case 'LA':
                        $payout_status = "LOSTALL";
                        $bet_status = "SETTLED";
                        break;
                    case 'WH':
                        $payout_status = "WINHALF";
                        $bet_status = "SETTLED";
                        break;
                    case 'LH':
                        $payout_status = "LOSTHALF";
                        $bet_status = "SETTLED";
                        break;
                    case 'D':
                        $payout_status = "DRAW";
                        $bet_status = "SETTLED";
                        break;
                    default:
                        $payout_status = "WAITING";
                        $bet_status = "WAITING";
                        break;
                }

                $betDetail = [
                    'bet_id' => "M8_" . $betTicket['id'],
                    'product' => "M8",
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => $betTicket['u'],
                    'stake' => $betTicket['b'],
                    'valid_stake' => $betTicket['b'],
                    'payout' => $betTicket['b'] + $betTicket['w'],
                    'winlose' => $betTicket['w'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['workdate'])->format('Y-m-d'),
                    'round_at' => Carbon::parse($betTicket['matchdatetime']),
                    'round_date' => Carbon::parse($betTicket['matchdatetime'])->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];
                $upserts[] = $betDetail;
                $ticketIds[] = $betTicket['fid'];
            }

            BetLog::upsertByChunk($upserts);
            if(count($ticketIds) != 0){
                _M8::updateBets(implode(',', $ticketIds));
            }
        }
    }
}
