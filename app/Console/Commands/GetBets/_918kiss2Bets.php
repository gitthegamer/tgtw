<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_918kiss2;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _918kiss2Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_918kiss2Bets {date?}';

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
        $date = Carbon::parse($date);
        $playerList = _918kiss2::get_player_list($date);

        foreach ($playerList as $player) {
            $betTickets = _918kiss2::product_logs($date, $player['playerid']);
            $this->process($player['playerid'], $betTickets);
        }

        return 0;
    }

    public function process($username, $betTickets)
    {
        if ($betTickets && count($betTickets)) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {

                $win = (float) $betTicket['win'];
                $bet = (float) $betTicket['bet'];

                if (($win - $bet) > 0) {
                    $payout_status = "WIN";
                } elseif ($win == $bet) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => $username . "_9K2_" . $betTicket['id'],
                    'product' => "918KISS2",
                    'game' => $betTicket['game'],
                    'category' => Product::CATEGORY_APP,
                    'username' => $username,
                    'stake' => $bet,
                    'valid_stake' => $bet,
                    'payout' => $win,
                    'winlose' => $win - $bet,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['date_time']),
                    'round_at' => Carbon::parse($betTicket['date_time']),
                    'round_date' => Carbon::parse($betTicket['date_time'])->format('Y-m-d'),
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
