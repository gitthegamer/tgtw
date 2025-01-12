<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\BG;
use App\Helpers\_AWC;
use App\Jobs\ProcessBGBetDetail;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\OperatorProduct;
use App\Models\Product;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class _AWCBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_AWCBets {date?}';

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
        $index = 0;
        foreach (_AWC::PLATFORM as $platform) {
            if ($index > 0) {
                // sleep(21);
            }
            $index++;
            $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->copy();
            $settingKey = $platform . "_last_bet_time";
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

            $date = $cachedDate ? Carbon::parse($cachedDate)->format('Y-m-d\TH:i:sP') : $date->copy()->subMinutes(30)->format('Y-m-d\TH:i:sP');

            $betTickets = _AWC::getBets($platform, $date);

            if (!$betTickets || empty($betTickets)) {
                Setting::updateOrCreate(
                    ['name' => $settingKey],
                    ['value' => null]
                );
                return;
            } else {
                $lastBetTicket = end($betTickets);
                if ($lastBetTicket && isset($lastBetTicket['updateTime'])) {
                    $lastUpdateTime = $lastBetTicket['updateTime'];
                    Setting::updateOrCreate(
                        ['name' => $settingKey],
                        ['value' => $lastUpdateTime]
                    );
                } else {
                    Setting::updateOrCreate(
                        ['name' => $settingKey],
                        ['value' => null]
                    );
                }
            }

            $this->process($betTickets);
        }

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winAmount'] - $betTicket['betAmount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winAmount'] - $betTicket['betAmount']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if ($betTicket['txStatus'] === 1 && ($betTicket['settleStatus'] === 0 || $betTicket['settleStatus'] === 1)) {
                    $bet_status = "SETTLED";
                } elseif ($betTicket['txStatus'] === 2) {
                    $bet_status = "VOID";
                } elseif ($betTicket['txStatus'] === 3) {
                    $bet_status = "SCRATCH";
                } elseif ($betTicket['txStatus'] === 5) {
                    $bet_status = "REFUND";
                } elseif ($betTicket['txStatus'] === 9) {
                    $bet_status = "INVALID";
                } elseif ($betTicket['txStatus'] === -1) {
                    $bet_status = "CANCEL";
                }

                $category = Product::CATEGORY_SLOTS;
                if ($betTicket['gameType'] == 'LIVE') {
                    $category = Product::CATEGORY_LIVE;
                } elseif ($betTicket['gameType'] == 'SLOT') {
                    $category = Product::CATEGORY_SLOTS;
                }
                // elseif($betTicket['gameType'] == 'FH'){
                //     $category = Product::CATEGORY_FISH;
                // }elseif($betTicket['gameType'] == 'EGAME'){
                //     $category = Product::CATEGORY_EGAME;
                // }elseif($betTicket['gameType'] == 'TABLE'){
                //     $category = Product::CATEGORY_TABLE;
                // }

                $betDetail = [
                    'bet_id' => $betTicket['platform'] . "_" . $betTicket['platformTxId'],
                    'product' => $betTicket['platform'],
                    'game' => $betTicket['gameName'],
                    'category' => $category,
                    'username' => $betTicket['userId'],
                    'stake' => $betTicket['betAmount'], // 下注
                    'valid_stake' => $betTicket['betAmount'], // turn over
                    'payout' => $betTicket['winAmount'], // 输赢 i place 1, win 0.5, = 1.5
                    'winlose' => $betTicket['winAmount'] - $betTicket['betAmount'], // 输赢 i place 1, win 0.5, = 0.5, i place 1, lose 1 = -1
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['txTime']),
                    'round_at' => Carbon::parse($betTicket['txTime']),
                    'round_date' => Carbon::parse($betTicket['txTime'])->format('Y-m-d'),
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
