<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\Playtech;
use App\Helpers\_Sexybrct;
use App\Jobs\ProcessBGBetDetail;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Illuminate\Console\Command;

class _PlaytechBets extends Command
{
    // SexyBaccarat
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_PlaytechBets {date?}';

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
        $startDate = $date->copy()->subMinutes(30)->format('Y-m-d H:i:s');
        $endDate = $date->copy()->format('Y-m-d H:i:s');
        $betTickets = Playtech::getBets($startDate, $endDate, 1);
        $this->process($betTickets); 
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if (doubleval($betTicket['WIN']) - doubleval($betTicket['BET']) > 0) {
                    $payout_status = "WIN";
                } elseif (doubleval($betTicket['WIN']) - doubleval($betTicket['BET'])  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }
                $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $betTicket['GAMEDATE']);
                
                $betDetail = [
                    'bet_id' => ($betTicket['GAMETYPE'] == 'Slot Machines' ? "PTS_" : "PTL_") . $betTicket['GAMECODE'],
                    'product' => $betTicket['GAMETYPE'] == 'Slot Machines' ? "PTS":"PTL",
                    'game' => $betTicket['GAMENAME'],
                    'category' => $betTicket['GAMETYPE'] == 'Slot Machines' ? Product::CATEGORY_SLOTS:Product::CATEGORY_LIVE,
                    'username' => $betTicket['PLAYERNAME'],
                    'stake' => $betTicket['BET'], // 下注
                    'valid_stake' => $betTicket['BET'], // turn over
                    'payout' => $betTicket['WIN'], // 输赢 i place 1, win 0.5, = 1.5
                    'winlose' => $betTicket['WIN'] - $betTicket['BET'], // 输赢 i place 1, win 0.5, = 0.5, i place 1, lose 1 = -1
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => 'SETTLED',
                    'account_date' => Carbon::parse($betTicket['GAMEDATE']),
                    'round_at' => Carbon::parse($betTicket['GAMEDATE']),
                    'round_date' => Carbon::parse($betTicket['GAMEDATE'])->format('Y-m-d'),
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
