<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_AdvantPlay;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _AdvantPlayBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_AdvantPlayBets {date?}';

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
        $endDate = $date->format('Y-m-d H:i:s');
        $startDate = $date->subDay()->format('Y-m-d H:i:s');
        $betTickets = _AdvantPlay::getBets($startDate, $endDate);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['TotalWin'] - $betTicket['TotalStake'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['TotalWin'] - $betTicket['TotalStake'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if (strtolower($betTicket['StatusCode']) == 'settled') {
                    $bet_status = "SETTLED";
                } else {
                    $bet_status = $betTicket['StatusCode'];
                }
                $betDetail = [
                    'bet_id' => "AP_".$betTicket['GameRoundId'],
                    'product' => "AP",
                    'game' => $betTicket['GameCode'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['PlayerId'],
                    'stake' => $betTicket['TotalStake'],
                    'valid_stake' => $betTicket['TotalStake'],
                    'payout' => $betTicket['TotalWin'],
                    'winlose' => $betTicket['TotalWin'] - $betTicket['TotalStake'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['BetTime']),
                    'round_at' => Carbon::parse($betTicket['BetTime']),
                    'round_date' => Carbon::parse($betTicket['BetTime'])->format('Y-m-d'),
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