<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_BTI;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;

class _BTIBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_BTIBets {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date'))->setTimezone('UTC') : now()->setTimezone('UTC');
        $endDateTime = $date->copy()->subMinutes(10)->format('Y-m-d\TH:i:s');
        $startDateTime = $date->copy()->subMinutes(20)->format('Y-m-d\TH:i:s');

        $betTickets = _BTI::getBets($startDateTime, $endDateTime);
        $this->process($betTickets);

        return 0;
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

                $accountDate = new DateTime($betTicket['winlost_datetime'], new DateTimeZone('GMT-4'));
                $roundAt =  new DateTime($betTicket['transaction_time'], new DateTimeZone('GMT-4'));
                $accountDate->setTimezone(new DateTimeZone('Asia/Shanghai'));
                $roundAt->setTimezone(new DateTimeZone('Asia/Shanghai'));

                $betDetail = [
                    'bet_id' => "IB_" . $betTicket['trans_id'],
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
        }
    }
}
