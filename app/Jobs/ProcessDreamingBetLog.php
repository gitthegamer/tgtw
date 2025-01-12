<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Dreaming;
use App\Helpers\_Evo888;
use App\Helpers\_PGS;
use App\Helpers\_Vpower;
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
use DateTimeZone;


class ProcessDreamingBetLog implements ShouldQueue
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

        $betTickets = _Dreaming::getBets();
        $this->process($betTickets); 

    }


    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            $ticketIds  = [];
            foreach ($betTickets as $betTicket) {
                if (($betTicket['winOrLoss'] - $betTicket['betPoints']) > 0) {
                    $payout_status = "WIN";
                } elseif ( ($betTicket['winOrLoss'] - $betTicket['betPoints']) < 0 ) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if($betTicket['isRevocation'] === 1){
                    $bet_status = "SETTLED"; 
                }elseif($betTicket['isRevocation'] === 2){
                    $bet_status = "REVOKED"; 
                }else{
                    $bet_status = "FREEZE";
                }
                
                $gameType = [
                    1 => 'Baccarat',
                    2 => 'InBaccarat',
                    3 => 'DragonTiger',
                    4 => 'Roulette',
                    5 => 'Sicbo',
                    6 => 'FanTan Roulette',
                    7 => 'Bull',
                    8 => 'Bid Baccarat',
                    11 => 'Three Cards',
                    14 => 'sedie',
                    16 => 'three face',
                    41 => 'blockchain baccarat',
                    42 => 'blockchain DragonTiger',
                    43 => 'blockchain Three Cards',
                    44 => 'blockchain Bull bull',
                    45 => 'blockchain ThreeFace',
                ];
    

                $betDetail = [
                    'bet_id' => 'DG_'.$betTicket['id'],
                    'product' => "DG",
                    'game' => $gameType[$betTicket['gameType']] ?? null,
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['userName'],
                    'stake' => $betTicket['betPoints'], // 下注
                    'valid_stake' => $betTicket['betPoints'], // turn over
                    'payout' => $betTicket['winOrLoss'],
                    'winlose' => $betTicket['winOrLoss'] - $betTicket['betPoints'],
                    'before_balance' => $betTicket['balanceBefore'],
                    'after_balance' => ($betTicket['balanceBefore'] + ($betTicket['winOrLoss'] - $betTicket['betPoints'])),
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['betTime'])->setTimezone('Asia/Singapore'),
                    'round_at' => Carbon::parse($betTicket['betTime'])->setTimezone('Asia/Singapore'),
                    'round_date' => Carbon::parse($betTicket['betTime'])->setTimezone('Asia/Singapore')->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' =>  json_encode($betTicket),
                ];
                $upserts[] = $betDetail;
                $ticketIds[] = $betTicket['id'];

            }

            BetLog::upsertByChunk($upserts);
            if(count($ticketIds) != 0){
                _Dreaming::updateBets($ticketIds);
            }
        }
    }
}
