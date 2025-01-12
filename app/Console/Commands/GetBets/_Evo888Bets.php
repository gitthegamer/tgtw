<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_AdvantPlay;
use App\Helpers\_Evo888;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _Evo888Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Evo888Bets {date?}';

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
        $endDate = $date->format('Y-m-d H:i:s');
        $startDate = $date->subMinutes(60)->format('Y-m-d H:i:s');

     
        $playerList = _Evo888::getPlayerList($startDate, $endDate);
        foreach ($playerList as $player) {
            $betTickets = _Evo888::getBets($startDate, $endDate, $player['UserName']);
            $this->process($player, $betTickets);
        }

        return 0;
    }

    public function process($member, $betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['Win'] - $betTicket['Bet'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['Win'] - $betTicket['Bet'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => "E8_" . $betTicket['Id'],
                    'product' => "E8",
                    'game' => $betTicket['GameName'],
                    'category' => Product::CATEGORY_APP,
                    'username' => $member['UserName'],
                    'stake' => $betTicket['Bet'],
                    'valid_stake' => $betTicket['Bet'],
                    'payout' => $betTicket['Win'],
                    'winlose' => $betTicket['Win'] - $betTicket['Bet'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['DateTime'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['DateTime'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['DateTime'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
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