<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_918kiss;
use App\Helpers\_Vpower;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class _VpowerBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_VpowerBets {date?}';

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
        
        $endDate = $date->copy()->format('Y-m-d H:i:s');
        $startDate = $date->copy()->subHour()->format('Y-m-d H:i:s');
        $betTickets = _Vpower::getBets($startDate, $endDate);
        SELF::process($betTickets);

        return 0;
    }

    public static function process($betTickets)
    {
        if ($betTickets && count($betTickets)) {
            $upserts = [];
            
            foreach ($betTickets as $betTicket) { 
              
                
                if (floatval($betTicket['win']) -  floatval($betTicket['pay']) > 0) {
                    $payout_status = "WIN";
                } elseif (floatval($betTicket['win']) -  floatval($betTicket['pay']) < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }
                $bet_status = "SETTLED";


                $before_balance = (float) $betTicket['startscore'];
                $after_balance = (float) $betTicket['finalscore'];

                $betDetail = [
                    'bet_id' => 'VP_'.$betTicket['uniqleid'],
                    'product' => "VP",
                    'game' => $betTicket['gameocode'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['username'],
                    'stake' => floatval($betTicket['pay']),
                    'valid_stake' => floatval($betTicket['pay']),
                    'payout' => floatval($betTicket['win']),
                    'winlose' => floatval($betTicket['win']) -  floatval($betTicket['pay']),
                    'before_balance' => $before_balance,
                    'after_balance' => $after_balance,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => date('Y-m-d H:i:s', $betTicket['time']),
                    'round_at' => date('Y-m-d H:i:s', $betTicket['time']),
                    'round_date' => Carbon::parse($betTicket['time'])->format('Y-m-d'),
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
