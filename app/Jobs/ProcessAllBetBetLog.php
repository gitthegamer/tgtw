<?php

namespace App\Jobs;

use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Allbet;
use App\Helpers\_Evo888;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
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
use Illuminate\Support\Facades\Log;

class ProcessAllBetBetLog implements ShouldQueue
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

        $date = $this->argument ? Carbon::parse($this->argument) : now();
        $endDate = $date->copy()->format('Y-m-d H:i:s');
        $startDate = $date->copy()->subHour()->format('Y-m-d H:i:s');
        $betTickets = _Allbet::getBets($startDate, $endDate);  
        $this->process($betTickets);

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
                    301 => 'Dragon Tiger',
                    401 => 'Roulette',
                    501 => 'Classic Pok Deng/Two Sides Pok Deng',
                    601 => 'Rock Paper Scissors',
                    801 => 'Bull Bull',
                    901 => 'Win Three Cards / Three Pictures',
                    702 => 'Ultimate Texas Holdem',
                    602 => 'Andar Bahar',
                    603 => 'Teen Patti 20-20',
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
