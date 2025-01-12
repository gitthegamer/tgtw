<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Dreaming;
use App\Helpers\_Evo888;
use App\Helpers\_King855;
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


class ProcessIBCBetLog implements ShouldQueue
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

        $betTickets = IBC::getBets();
        $this->process($betTickets);

    }


    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                switch (strtoupper($betTicket['ticket_status'])) {
                    case "HALF WON":
                        $payout_status = "HALF WIN";
                        $bet_status = "SETTLED";
                        break;
                    case "HALF LOSE":
                        $payout_status = "HALF LOSE";
                        $bet_status = "SETTLED";
                        break;
                    case "WON":
                        $payout_status = "WIN";
                        $bet_status = "SETTLED";
                        break;
                    case "LOSE":
                        $payout_status = "LOSE";
                        $bet_status = "SETTLED";
                        break;
                    case "DRAW":
                        $payout_status = "DRAW";
                        $bet_status = "SETTLED";
                        break;
                    case "VOID":
                        $payout_status = "VOID";
                        $bet_status = "VOID";
                        break;
                    case "RUNNING":
                        $payout_status = "RUNNING";
                        $bet_status = "RUNNING";
                        break;
                    case "REJECT":
                        $payout_status = "REJECT";
                        $bet_status = "REJECT";
                        break;
                    case "REFUND":
                        $payout_status = "REFUND";
                        $bet_status = "REFUND";
                        break;
                    case "WAITING":
                        $payout_status = "WAITING";
                        $bet_status = "WAITING";
                        break;
                    default:
                        $payout_status = "WAITING";
                        $bet_status = "WAITING";
                        break;
                }
                $roundAt =  new DateTime($betTicket['transaction_time'], new DateTimeZone('GMT-4'));
                $roundAt->setTimezone(new DateTimeZone('Asia/Shanghai'));
                
                $betDetail = [
                    'bet_id' => "IB_".$betTicket['trans_id'],
                    'product' => "IB",
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => strtoupper($betTicket['vendor_member_id']),
                    'stake' => $betTicket['stake'],
                    'valid_stake' => $betTicket['stake'],
                    'payout' => $betTicket['stake'] + $betTicket['winlost_amount'],
                    'winlose' => $betTicket['winlost_amount'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['winlost_datetime'])->format('Y-m-d'),
                    'round_at' => $roundAt->format('Y-m-d H:i:s'),
                    'round_date' => $roundAt->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];

                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
