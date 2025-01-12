<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Sv388;
use App\Jobs\ProcessBGBetDetail;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\OperatorProduct;
use App\Models\Product;
use App\Models\Setting;
use App\Jobs\ProcessSv388HourlyBetLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _Sv388Bets extends Command
{
    // SexyBaccarat
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GetBets:_Sv388Bets {date?}';

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
        $settingKey = "SV388_last_bet_time";
        $cachedDate = Setting::get($settingKey);
        if ($cachedDate) {
            $cachedDateCarbon = Carbon::parse($cachedDate);
            if (now()->gte($cachedDateCarbon->addDay())) {
                Setting::updateOrCreate(
                    ['name' => $settingKey],
                    ['value' => null]
                );
                $cachedDate = null;
            }
        }
        $date = $cachedDate ? Carbon::parse($cachedDate)->format('Y-m-d\TH:i:sP') : $date->copy()->subMinute()->format('Y-m-d\TH:i:sP');

        $betTickets = _Sv388::getBets($date);

        if (!$betTickets || empty($betTickets)) {
            echo "No bet tickets found\n";
            Setting::updateOrCreate(
                ['name' => $settingKey],
                ['value' => null]
            );
            return;
        } else {
            $lastBetTicket = end($betTickets);
            if ($lastBetTicket && isset($lastBetTicket['updateTime'])) {
                echo "Last bet ticket time: {$lastBetTicket['updateTime']}\n";
                $lastUpdateTime = $lastBetTicket['updateTime'];
                Setting::updateOrCreate(
                    ['name' => $settingKey],
                    ['value' => $lastUpdateTime]
                );
            } else {
                echo "got but error.\n";
                Setting::updateOrCreate(
                    ['name' => $settingKey],
                    ['value' => null]
                );
            }
        }

        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['realWinAmount'] - $betTicket['betAmount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['realWinAmount'] - $betTicket['betAmount']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if ($betTicket['txStatus'] == 1) {
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

                $betDetail = [
                    'bet_id' => $betTicket['platform'] . "_" . $betTicket['platformTxId'],
                    'product' => $betTicket['platform'],
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_SPORTS,
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
