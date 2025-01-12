<?php

namespace App\Jobs;

use App\Helpers\_Sexybrct;

use App\Models\BetLog;
use App\Models\Product;
use App\Models\Setting;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class ProcessSexybcrtBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $argument;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($argument)
    {
        $this->argument = $argument;
        $this->queue = 'fetch_bet_logs';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $date = $this->argument ? Carbon::parse($this->argument) : now()->copy();
        $settingKey = "SEXYBCRT_last_bet_time";
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

        $betTickets = _Sexybrct::getBets($date);

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
                    'product' => "SEXYBCRT",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['userId'],
                    'stake' => $betTicket['betAmount'], // 下注
                    'valid_stake' => $betTicket['turnover'], // turn over
                    'payout' => $betTicket['realWinAmount'], // 输赢 i place 1, win 0.5, = 1.5
                    'winlose' => $betTicket['realWinAmount'] - $betTicket['betAmount'], // 输赢 i place 1, win 0.5, = 0.5, i place 1, lose 1 = -1
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
