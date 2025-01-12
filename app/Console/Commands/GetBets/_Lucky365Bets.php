<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\Mega888;
use App\Helpers\_Jili;
use App\Helpers\_Lucky365;
use App\Http\Helpers;
use App\Models\Bet;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _Lucky365Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Lucky365Bets {date?}';

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
        $startDate = $date->copy();
        $startDate = $startDate->subMinutes(15)->format('Y-m-d H:i:s');
        $betTickets = _Lucky365::getBets($startDate, $endDate);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['validWin'] - $betTicket['validBet'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['validWin'] - $betTicket['validBet']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED"; 

                $betDetail = [
                    'bet_id' => 'L365_'.$betTicket['orderCode'],
                    'product' => "L365",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['loginId'],
                    'stake' => $betTicket['validBet'],
                    'valid_stake' => $betTicket['validBet'],
                    'payout' => $betTicket['validWin'], 
                    'winlose' => $betTicket['validWin'] - $betTicket['validBet'], 
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['actionDate']),
                    'round_at' => Carbon::parse($betTicket['actionDate']),
                    'round_date' => Carbon::parse($betTicket['actionDate'])->format('Y-m-d'),
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
