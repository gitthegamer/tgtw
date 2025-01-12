<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_FunkyGames;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _FunkyGamesBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_FunkyGamesBets {date?}';

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
        //start date is 15min before and end date is now
        $startDate = $date->copy()->subMinutes(30)->toIso8601String();
        $endDate = $date->copy()->toIso8601String();

        $betTickets = _FunkyGames::getBets($startDate, $endDate);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winloss'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winloss'] == 0) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $adjustedBetTime = Carbon::parse($betTicket['betTime'])->addHours(8);
               
                $betDetail = [
                    'bet_id' => "FUNKYGAMES_" . $betTicket['refNo'],
                    'product' => "FUNKYGAMES",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['playerId'],
                    'stake' => $betTicket['betAmount'],
                    'valid_stake' => $betTicket['effectiveStake'],
                    'payout' => $betTicket['betAmount'] + $betTicket['winloss'],
                    'winlose' => $betTicket['winloss'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => $betTicket['statementDate'],
                    'round_at' => $adjustedBetTime,
                    'round_date' => $adjustedBetTime->format('Y-m-d'),
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