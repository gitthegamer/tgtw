<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_RCB988;
use App\Helpers\_Sexybrct;
use App\Models\BetLog;
use App\Models\Product;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _RCB988Bets extends Command
{
    // SexyBaccarat
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_RCB988Bets {date?}';

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
        $settingKey = "RCB988_last_bet_time";
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

        $betTickets = _RCB988::getBets($date);

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
                $winlose = $betTicket['realWinAmount'] - $betTicket['betAmount'];

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose  < 0) {
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


                $valid_stake = floatval($betTicket['betAmount']);
                if ($winlose > 0) {
                    if ($winlose >= floatval($betTicket['betAmount'])) {
                        $valid_stake = floatval($betTicket['betAmount']);
                    } else {
                        $valid_stake = $winlose;
                    }
                } else if ($winlose < 0) {
                    $valid_stake = abs($winlose);
                }

                $betDetail = [
                    'bet_id' => $betTicket['platform'] . "_" . $betTicket['platformTxId'],
                    'product' => "RCB988",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_HORSE,
                    'username' => strtoupper($betTicket['userId']),
                    'stake' => $betTicket['betAmount'], // 下注
                    'valid_stake' => $valid_stake, // turn over
                    'payout' => $betTicket['realWinAmount'], // 输赢 i place 1, win 0.5, = 1.5
                    'winlose' => $winlose, // 输赢 i place 1, win 0.5, = 0.5, i place 1, lose 1 = -1
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
