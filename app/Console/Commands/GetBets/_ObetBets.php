<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Obet33;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _ObetBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_ObetBets {date?}';

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
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->copy();
        $endDate = $date->format('YmdHis');
        $startDate = $date->subMinute(15)->format('YmdHis');
        $betTickets = _Obet33::getBets($startDate, $endDate);
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                switch ($betTicket['Status']) {
                    case "-1":
                        $payout_status = "PENDING";
                        break;
                    case "0":
                        $payout_status = "ACCEPTED";
                        break;
                    case "1":
                        $payout_status = "REJECTED";
                        break;
                    case "4":
                        $payout_status = "CANCELLED";
                        break;
                    case "5":
                        $payout_status = "REFUNDED";
                        break;
                    default:
                        $payout_status = "WAITING";
                        break;
                }

                switch ($betTicket['Tresult']) {
                    case "0":
                        // $bet_status = "LOSE";
                        $bet_status = "SETTLED";
                        break;
                    case "1":
                        // $bet_status = "WIN";
                        $bet_status = "SETTLED";
                        break;
                    case "2":
                        // $bet_status = "DRAW";
                        $bet_status = "SETTLED";
                        break;
                    case "3":
                        // $bet_status = "WIN HALF";
                        $bet_status = "SETTLED";
                        break;
                    case "4":
                        // $bet_status = "LOSE HALF";
                        $bet_status = "SETTLED";
                        break;
                    default:
                        // $bet_status = "WAITING";
                        $bet_status = "WAITING";
                }

                $winlose = floatval($betTicket['Wamt'] ?? 0);
                $valid_stake = floatval($betTicket['Bamt']);
                if ($winlose > 0) {
                    if ($winlose >= floatval($betTicket['Bamt'])) {
                        $valid_stake = floatval($betTicket['Bamt']);
                    } else {
                        $valid_stake = $winlose;
                    }
                } else if ($winlose < 0) {
                    $valid_stake = abs($winlose);
                }


                $betDetail = [
                    'bet_id' => "OB_" . $betTicket['Id'],
                    'product' => "OB",
                    'game' => $betTicket['Sport'],
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => $betTicket['User'],
                    'stake' => $betTicket['Bamt'],
                    'valid_stake' => $valid_stake,
                    'payout' => floatval($betTicket['Bamt']) + $betTicket['Wamt'] ?? 0,
                    'winlose' => floatval($betTicket['Wamt'] ?? 0),
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['SDate'])->format('Y-m-d'),
                    'round_at' => Carbon::parse($betTicket['ADate']),
                    'round_date' => Carbon::parse($betTicket['ADate'])->format('Y-m-d'),
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
