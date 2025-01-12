<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Allbet;
use App\Helpers\_Sexybrct;
use App\Http\Helpers;
use App\Jobs\ProcessBGBetDetail;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _AllbetBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Allbets {date?}';

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
        $betTickets = _Allbet::getBets($startDate, $endDate);  
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winOrLossAmount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winOrLossAmount'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if($betTicket['status'] == 111){
                    $bet_status = "SETTLED";
                }elseif($betTicket['status'] == 120){
                    $bet_status = "REFUND";
                }else{
                    $bet_status = "NOT SETTLED";
                }
                

                $gameType = [
                    101 => 'Normal Baccarat',
                    102 => 'VIP Baccarat',
                    103 => 'Quick Baccarat',
                    104 => 'See Card Baccarat',
                    110 => 'Insurance Baccarat',
                    201 => 'Sicbo(HiLo)',
                    202 => 'Fish Prawn Crab',
                    301 => 'Dragon Tiger',
                    401 => 'Roulette',
                    501 => 'Classic Pok Deng/Two Sides Pok Deng',
                    601 => 'Rock Paper Scissors',
                    801 => 'Bull Bull',
                    901 => 'Win Three Cards / Three Pictures',
                    702 => 'Ultimate Texas Holdem',
                    602 => 'Andar Bahar',
                    603 => 'Teen Patti 20-20',
                    703 => 'Casino War',
                ];

                $betDetail = [
                    'bet_id' => "AB_" . $betTicket['betNum'],
                    'product' => "AB",
                    'game' => isset($gameType[$betTicket['gameType']]) ? $gameType[$betTicket['gameType']] : $betTicket['gameType'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['player'],
                    'stake' => $betTicket['betAmount'],
                    'valid_stake' => $betTicket['betAmount'],
                    'payout' => $betTicket['betAmount'] + $betTicket['winOrLossAmount'],
                    'winlose' => $betTicket['winOrLossAmount'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['gameRoundEndTime']),
                    'round_at' => Carbon::parse($betTicket['gameRoundEndTime']),
                    'round_date' => Carbon::parse($betTicket['gameRoundEndTime'])->format('Y-m-d'),
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