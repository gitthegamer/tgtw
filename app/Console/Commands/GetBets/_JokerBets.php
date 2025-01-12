<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\Joker;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers;

class _JokerBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_JokerBets {date?}';

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
        $betTickets = Joker::getBets($date, true);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['Result'] - $betTicket['Amount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['Result'] == $betTicket['Amount']) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => "JK_" . $betTicket['RoundID'],
                    'product' => "JK",
                    'game' => $betTicket['GameCode'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['Username'],
                    'stake' => $betTicket['Amount'],
                    'valid_stake' => $betTicket['Amount'],
                    'payout' => $betTicket['Result'],
                    'winlose' => $betTicket['Result'] - $betTicket['Amount'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['Time']),
                    'round_at' => Carbon::parse($betTicket['Time']),
                    'round_date' => Carbon::parse($betTicket['Time'])->format('Y-m-d'),
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