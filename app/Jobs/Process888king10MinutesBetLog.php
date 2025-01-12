<?php

namespace App\Jobs;

use App\Helpers\_888king2;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class Process888king10MinutesBetLog implements ShouldQueue
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
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $date = $this->argument ? Carbon::parse($this->argument) : now();
        // $endDate = $date->copy();
        // $endDate->addMinutes(10);

        $versionKeyList = BetLog::where('round_at', '>=', $date->copy()->subHour())
            ->where('product', '8K')
            ->where('bet_status', '!=', 'SETTLED')
            ->pluck('key')
            ->unique()
            ->values();

        foreach ($versionKeyList as $versionKey) {
            if ($versionKey == null) continue;

            $result = _888king2::getBets($versionKey, true);
            if (is_array($result) && isset($result['data'], $result['key'])) {
                $betTickets = $result['data'];
                $key        = $result['key'];
                
                $this->process($betTickets, $key);
            }
        }
    }

    public function process($betTickets, $key)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                if ($betTicket['payout_amount'] - $betTicket['bet_stake'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['payout_amount'] - $betTicket['bet_stake']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED";
                if (strtolower($betTicket['status']) != 'done') {
                    $bet_status = $betTicket['status'];
                }

                $betDetail = [
                    'bet_id' => '8K_' . $betTicket['ticket_id'],
                    'product' => "8K",
                    'game' => $betTicket['game_code'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['username'],
                    'stake' => $betTicket['bet_stake'],
                    'valid_stake' => $betTicket['bet_stake'],
                    'payout' => $betTicket['payout_amount'],
                    'winlose' => $betTicket['payout_amount'] - $betTicket['bet_stake'],
                    'before_balance' => $betTicket['before_balance'],
                    'after_balance' => $betTicket['after_balance'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['report_date']),
                    'round_at' => Carbon::parse($betTicket['report_date']),
                    'round_date' => Carbon::parse($betTicket['report_date'])->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                    'key' => $key
                ];

                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
