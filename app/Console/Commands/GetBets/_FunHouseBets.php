<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\Mega888;
use App\Helpers\_AdvantPlay;
use App\Helpers\_Funhouse;
use App\Helpers\_Jili;
use App\Models\Bet;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _FunHouseBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_FunHouseBets {date?}';

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
        
        $endDate = $date->copy()->getTimestamp();
        $startDate = $date->copy()->subDay()->getTimestamp();
        $betTickets = _Funhouse::getBets($startDate, $endDate, 1);
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['win'] - $betTicket['bet'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['win'] - $betTicket['bet'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED";
                
                $betDetail = [
                    'bet_id' => 'FH_'.$betTicket['round_id'],
                    'product' => "FH",
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['external_player_id'],
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $betTicket['bet'],
                    'payout' => $betTicket['win'] - $betTicket['bet'] <= 0 ? 0: $betTicket['win'] - $betTicket['bet'], 
                    'winlose' => $betTicket['win'] - $betTicket['bet'], 
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['date'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['date'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['date'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
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
