<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_WMCasino;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _WMCasinoBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_WMCasinoBets {date?}';

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
        $start_date = $date->copy()->subMinutes(60)->format('YmdHis');
        $end_date = $date->copy()->format('YmdHis');

        $betTickets = _WMCasino::getBets($start_date, $end_date);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winLoss'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winLoss'] == 0) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => "WMCASINO_" . $betTicket['betId'],
                    'product' => "WMCASINO",
                    'game' => $betTicket['gname'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => strtoupper($betTicket['user']),
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $betTicket['validbet'],
                    'payout' => $betTicket['winLoss'] + $betTicket['bet'],
                    'winlose' => $betTicket['winLoss'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['settime']),
                    'round_at' => Carbon::parse($betTicket['betTime']),
                    'round_date' => Carbon::parse($betTicket['betTime'])->format('Y-m-d'),
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
