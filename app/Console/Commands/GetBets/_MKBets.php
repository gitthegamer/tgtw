<?php

namespace App\Console\Commands\GetBets;

use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Helpers\_MK;

class _MKBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_MKBets {date?}';

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
        $starttime = $date->copy()->subMinutes(60)->format('Y-m-d\TH:i:s');
        $endtime = now()->format('Y-m-d\TH:i:s');
        $betTickets = _MK::getBets($starttime, $endtime);
        
        if (!empty($betTickets)) {
            $this->process($betTickets);
        }

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
                    'bet_id' => 'MK_'.$betTicket['orderCode'],
                    'product' => "MK",
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
