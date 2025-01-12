<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_NextSpin;
use App\Helpers\Joker;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _NextSpinBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_NextSpinBets {date?}';

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
        //start date is 15min before and end date is now
        $start_date = $date->copy()->subMinutes(15)->format('Ymd\THis');
        $end_date = $date->copy()->format('Ymd\THis');

        $betTickets = _NextSpin::getBets($start_date, $end_date);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winLoss'] - $betTicket['betAmount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winLoss'] == $betTicket['betAmount']) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => "NEXTSPIN_" . $betTicket['ticketId'],
                    'product' => "NEXTSPIN",
                    'game' => $betTicket['gameCode'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['acctId'],
                    'stake' => $betTicket['betAmount'],
                    'valid_stake' => $betTicket['betAmount'],
                    'payout' => $betTicket['winLoss'] +$betTicket['betAmount'],
                    'winlose' => $betTicket['winLoss'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['ticketTime']),
                    'round_at' => Carbon::parse($betTicket['ticketTime']),
                    'round_date' => Carbon::parse($betTicket['ticketTime'])->format('Y-m-d'),
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