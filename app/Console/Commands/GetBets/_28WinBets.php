<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_28Win;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _28WinBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_28WinBets {date?}';

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

        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : Carbon::now();

        foreach (_28Win::TYPES as $drawType => $names) {
            $betTickets = _28Win::winLoss($drawType, $date);
            usleep(500000);
            $this->process($betTickets, $drawType, $date);
        }

        return 0;
    }

    public function process($betTickets, $drawType, $date)
    {

        $date = $date->format('Y-m-d');

        if (isset($betTickets['winLoss']['loginName'])) {
            $upserts = [];
            $total = round((float)str_replace(',', '', $betTickets['winLoss']['total']), 2);
            $turnover = round((float)str_replace(',', '', $betTickets['winLoss']['turnover']), 2);

            if ($total < 0) {
                $payout_status = "LOSE";
                $bet_status = "SETTLED";
            } else if ($total > 0) {
                $payout_status = "WIN";
                $bet_status = "SETTLED";
            } else {
                $payout_status = "DRAW";
                $bet_status = "SETTLED";
            }

            $betDetail = [
                'bet_id' => '28W_' . $date . '_' . $drawType . '_' . $betTickets['winLoss']['loginName'],
                'product' => "28W",
                'game' => $drawType,
                'category' => Product::CATEGORY_LOTTERY,
                'username' => $betTickets['winLoss']['loginName'],
                'stake' => $turnover,
                'valid_stake' => $turnover,
                'payout' => $turnover + $total,
                'winlose' => $total,
                'jackpot_win' => 0,
                'progressive_share' => 0,
                'payout_status' => $payout_status,
                'bet_status' => $bet_status,
                'account_date' => Carbon::parse($date),
                'round_at' => Carbon::parse($date),
                'round_date' => Carbon::parse($date)->format('Y-m-d'),
                'modified_at' => now(),
                'modified_date' => now()->format('Y-m-d'),
                'bet_detail' => json_encode($betTickets['winLoss']),
            ];

            $upserts[] = $betDetail;

            BetLog::upsertByChunk($upserts);
        } else {
            if (count($betTickets) == 0) {
                return;
            }
            $upserts = [];
            foreach ($betTickets['winLoss'] as $betTicket) {
                $total = round((float)str_replace(',', '', $betTicket['total']), 2);
                $turnover = round((float)str_replace(',', '', $betTicket['turnover']), 2);

                if ($total < 0) {
                    $payout_status = "LOSE";
                    $bet_status = "SETTLED";
                } else if ($total > 0) {
                    $payout_status = "WIN";
                    $bet_status = "SETTLED";
                } else {
                    $payout_status = "DRAW";
                    $bet_status = "SETTLED";
                }

                $betDetail = [
                    'bet_id' => '28W_' . $date . '_' . $drawType . '_' . $betTicket['loginName'],
                    'product' => "28W",
                    'game' => $drawType,
                    'category' => Product::CATEGORY_LOTTERY,
                    'username' => $betTicket['loginName'],
                    'stake' => $turnover,
                    'valid_stake' => $turnover,
                    'payout' => $turnover + $total,
                    'winlose' => $total,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($date),
                    'round_at' => Carbon::parse($date),
                    'round_date' => Carbon::parse($date)->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];

                $upserts[] = $betDetail;

                BetLog::upsertByChunk($upserts);
            }
        }




        return 0;
    }
}
