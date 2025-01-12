<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Live22;
use App\Helpers\_PP;
use App\Helpers\_PPLive;
use App\Models\BetLog;
use App\Models\BetStake;
use App\Models\Product;
use App\Modules\_PPController;
use Carbon\Carbon;
use App\Models\MemberAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _PPLiveBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_PPLiveBets {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get PPLive bets';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->copy();
        $defaultTimestamp = $date->subMinutes(20)->timestamp * 1000;  // Convert to milliseconds
        $cachedTimestamp = Cache::get('pplive_timepoint', $defaultTimestamp);

        $betTickets = _PPLive::getBetsMember($cachedTimestamp);

        if (empty($betTickets)) {
            return 0; 
        }

        $this->process($betTickets);

        return 0;
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
