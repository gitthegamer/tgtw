<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Vpower;
use App\Helpers\_XE88;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class _Xe88Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Xe88Bets {date?}';

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
        $startDate = $date->copy()->format('Y-m-d');
        $startTime = $date->copy()->subHour()->format('HH:mm:ss');
        $endTime = $date->copy()->format('HH:mm:ss');

        $betTickets = _XE88::getBets($startDate, $startTime, $endTime);
        SELF::process($betTickets);

        return 0;
    }

    public static function process($betTickets)
    {
        if ($betTickets && count($betTickets)) {
            $upserts = [];
            
            foreach ($betTickets as $betTicket) { 
              
                
                if (($betTicket['win'] - $betTicket['bet']) > 0) {
                    $payout_status = "WIN";
                } elseif (($betTicket['win'] - $betTicket['bet']) < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => 'XE88_'.$betTicket['id'],
                    'product' => "XE88",
                    'game' => $betTicket['gamename'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['username'],
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $betTicket['bet'],
                    'payout' => $betTicket['win'],
                    'winlose' => $betTicket['win'] - $betTicket['bet'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => date('Y-m-d H:i:s', strtotime($betTicket['logtime'])),
                    'round_at' => date('Y-m-d H:i:s', strtotime($betTicket['logtime'])),
                    'round_date' => Carbon::parse($betTicket['logtime'])->format('Y-m-d'),
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
