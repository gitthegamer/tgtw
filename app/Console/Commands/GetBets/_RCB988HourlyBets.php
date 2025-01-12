<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_RCB988;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _RCB988HourlyBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_RCB988HourlyBets';

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
        $now = now();
        $startTime = $now->copy()->subHours(2)->startOfHour()->format('Y-m-d\TH:i:s');
        $endTime = $now->copy()->subHour()->startOfHour()->format('Y-m-d\TH:i:s');

        $betTickets = _RCB988::getHourlyBetLog($startTime, $endTime);
        $this->process($betTickets);

        return 0;
    }



    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winAmount'] - $betTicket['betAmount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winAmount'] - $betTicket['betAmount'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if ($betTicket['txStatus'] == 1 && ($betTicket['settleStatus'] == 0 || $betTicket['settleStatus'] == 1)) {
                    $bet_status = "SETTLED";
                } elseif ($betTicket['txStatus'] == 2) {
                    $bet_status = "VOID";
                } elseif ($betTicket['txStatus'] == 3) {
                    $bet_status = "SCRATCH";
                } elseif ($betTicket['txStatus'] == 5) {
                    $bet_status = "REFUND";
                } elseif ($betTicket['txStatus'] == 9) {
                    $bet_status = "INVALID";
                } elseif ($betTicket['txStatus'] == -1) {
                    $bet_status = "CANCEL";
                }

                $category = Product::CATEGORY_SLOTS;
                if ($betTicket['gameType'] == 'LIVE') {
                    $category = Product::CATEGORY_LIVE;
                } elseif ($betTicket['gameType'] == 'SLOT') {
                    $category = Product::CATEGORY_SLOTS;
                }

                $betDetail = [
                    'bet_id' => $betTicket['platform'] . "_" . $betTicket['platformTxId'],
                    'product' => $betTicket['platform'],
                    'game' => $betTicket['gameName'],
                    'category' => $category,
                    'username' => $betTicket['userId'],
                    'stake' => $betTicket['betAmount'], // 下注
                    'valid_stake' => $betTicket['turnover'], // turn over
                    'payout' => $betTicket['winAmount'], // 输赢 i place 1, win 0.5, = 1.5
                    'winlose' => $betTicket['winAmount'] - $betTicket['betAmount'], // 输赢 i place 1, win 0.5, = 0.5, i place 1, lose 1 = -1
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['txTime']),
                    'round_at' => Carbon::parse($betTicket['betTime']),
                    'round_date' => Carbon::parse($betTicket['betTime'])->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                    'key' => Carbon::parse($betTicket['updateTime'])
                ];
                $upserts[] = $betDetail;
            }
            BetLog::upsertByChunk($upserts);
        }
    }
}
