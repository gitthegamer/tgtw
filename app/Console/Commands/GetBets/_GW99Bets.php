<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_GW99;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class _GW99Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_GW99Bets {date?}';

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
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now();
        $endDate = $date->copy()->format('Y-m-d H:i:s') . "." . substr(now()->copy()->format('u'), 0, 3);
        $startDate = $date->copy()->subMinutes(30)->format('Y-m-d H:i:s') . "." . substr(now()->copy()->format('u'), 0, 3);
        $betTickets = _GW99::getBets($startDate, $endDate, 1);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {

            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['availTotalWin'] - $betTicket['betCoin'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['availTotalWin'] - $betTicket['betCoin']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => 'GW99_' . $betTicket['gameSerialId'],
                    'product' => "GW99",
                    'game' => $betTicket['themeId'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['userName'],
                    'stake' => $betTicket['betCoin'],
                    'valid_stake' => $betTicket['betCoin'],
                    'payout' => $betTicket['availTotalWin'],
                    'winlose' => $betTicket['availTotalWin'] - $betTicket['betCoin'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['createTime']),
                    'round_at' => Carbon::parse($betTicket['createTime']),
                    'round_date' => Carbon::parse($betTicket['createTime'])->format('Y-m-d'),
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
