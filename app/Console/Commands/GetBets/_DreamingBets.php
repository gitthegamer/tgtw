<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Dreaming;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class _DreamingBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_DreamingBets';

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
        $betTickets = _Dreaming::getBets();
        $this->process($betTickets); 
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            $ticketIds = [];

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
                    'stake' => $betTicket['betPoints'], 
                    'valid_stake' => $betTicket['betPoints'], 
                    'payout' => $betTicket['winOrLoss'],
                    'winlose' => $betTicket['winOrLoss'] - $betTicket['betPoints'], 
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
