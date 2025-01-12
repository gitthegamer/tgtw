<?php

namespace App\Jobs;

use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_AWC;
use App\Helpers\_Evo888;
use App\Helpers\_Playboy;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Setting;
use App\Modules\_PlayboyController;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessAWCBetLogDetails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $platform;
    protected $date;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($platform, $date)
    {
        $this->platform = $platform;
        $this->date = $date;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $settingKey = $this->platform . "_last_bet_time";
        $betTickets = _AWC::getBets($this->platform, $this->date);
        Log::debug("Processing for platform: $this->platform");
        Log::debug("Processing for date: $this->date");

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
