<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Evolution;
use App\Http\Helpers;
use App\Jobs\ProcessBGBetDetail;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class _EvolutionDailyBets extends Command
{
    // SexyBaccarat
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_EvolutionDailyBets {date?}';

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
        $startDate = $date->copy()->startOfDay()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
        $endDate = $date->copy()->endOfDay()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
        $betTickets = _Evolution::getBets($startDate, $endDate);

        $this->info($startDate);
        $this->info($endDate);
        $this->info(json_encode($betTickets));

        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                foreach ($betTicket['participants'] as $participant) {
                    $totalStake = 0;
                    $totalPayout = 0;

                    foreach ($participant['bets'] as $bet) {
                        $totalStake += $bet['stake'];
                        $totalPayout += $bet['payout'];
                    }

                    if ($totalPayout - $totalStake > 0) {
                        $payout_status = "WIN";
                    } elseif ($totalPayout - $totalStake < 0) {
                        $payout_status = "LOSE";
                    } else {
                        $payout_status = "DRAW";
                    }

                    $betDetail = [
                        'bet_id' => "EVO_" . $participant['playerGameId'],
                        'product' => "EVO",
                        'game' => $betTicket['table']['name'],
                        'category' => Product::CATEGORY_LIVE,
                        'username' => $participant['playerId'],
                        'stake' => $totalStake, // 下注
                        'valid_stake' => $totalStake, // turn over
                        'payout' => $totalPayout, // 输赢
                        'winlose' => $totalPayout - $totalStake, // 输赢
                        'jackpot_win' => 0,
                        'progressive_share' => 0,
                        'payout_status' => $payout_status,
                        'bet_status' => strtolower($betTicket['status']) == "resolved" ? "SETTLED" : strtoupper($betTicket['status']),
                        'account_date' => Carbon::parse($betTicket['settledAt'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                        'round_at' => Carbon::parse($betTicket['settledAt'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                        'round_date' => Carbon::parse($betTicket['settledAt'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
                        'modified_at' => now(),
                        'modified_date' => now()->format('Y-m-d'),
                        'bet_detail' => json_encode($betTicket),
                    ];
                    $upserts[] = $betDetail;
                }
            }
            BetLog::upsertByChunk($upserts);
        }
    }
}
