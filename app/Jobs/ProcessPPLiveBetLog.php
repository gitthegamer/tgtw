<?php

namespace App\Jobs;

use App\Helpers\_PPLive;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessPPLiveBetLog implements ShouldQueue
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
        $date = $this->argument ? Carbon::parse($this->argument) : now()->copy();
        $defaultTimestamp = $date->subMinutes(20)->timestamp * 1000;  // Convert to milliseconds
        $cachedTimestamp = Cache::get('pplive_timepoint', $defaultTimestamp);

        $betTickets = _PPLive::getBetsMember($cachedTimestamp);

        if (empty($betTickets)) {
            return 0;
        }

        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                if (!isset($betTicket['playSessionID']) || !isset($betTicket['type'])) {
                    continue;
                }

                $bet_status = 'SETTLED';
                $stake = $betTicket['bet'];
                $payout = $betTicket['win'];
                if ($payout > $stake) {
                    $winlose = $payout - $stake;
                    $payout_status = 'WIN';
                } elseif ($payout == $stake) {
                    $winlose = 0;
                    $payout_status = 'DRAW';
                } else {
                    $winlose = $payout - $stake;
                    $payout_status = 'LOSE';
                }

                $betDetail = [
                    'bet_id' => "PPLIVE_" . $betTicket['playSessionID'],
                    'product' => "PPLIVE",
                    'game' => $betTicket['gameID'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['extPlayerID'],
                    'stake' => $stake,
                    'valid_stake' => $stake,
                    'payout' => $payout,
                    'winlose' => $winlose,
                    'jackpot_win' => $betTicket['jackpot'] ?? 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['endDate'])->format('Y-m-d'),
                    'round_at' => Carbon::parse($betTicket['endDate'])->format('Y-m-d H:i:s'),
                    'round_date' => Carbon::parse($betTicket['endDate'])->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                    'key' => null,
                ];

                $upserts[] = $betDetail;
            }

            if (count($upserts) > 0) {
                BetLog::upsertByChunk($upserts);
            }
        }
    }
}
