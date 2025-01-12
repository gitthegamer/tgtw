<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Rich88;
use App\Models\BetLog;
use App\Models\Product;
use App\Modules\_Rich88Controller;
use Carbon\Carbon;
use App\Models\MemberAccount;
use Illuminate\Console\Command;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _Rich88Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Rich88Bets {date?}';

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
        $starttime = $date->copy()->subMinutes(60)->utc()->format('Y-m-d H:i:s');
        $endtime = now()->utc()->format('Y-m-d H:i:s');
        $betTickets = _Rich88::getBets($starttime, $endtime);
        if (!empty($betTickets)) {
            $this->process($betTickets);
        } else {
            echo "No bet tickets found.";
        }

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                echo json_encode($betTicket) . "\n";
                $stake = $betTicket['bet'];
                $valid_stake = $betTicket['bet_valid'];
                $winlose = $betTicket['profit'];
                $payout = $betTicket['profit'] + $betTicket['bet'];

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }
                $bet_status = "SETTLED";


                $betDetail = [
                    'bet_id' => $betTicket['record_id'],
                    'product' => "RICH88",
                    'game' => $betTicket['game_code'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['account'],
                    'stake' => $stake,
                    'valid_stake' => $valid_stake,
                    'payout' => $payout,
                    'winlose' => $winlose,
                    'jackpot_win' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['created_at'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['round_start_at'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['round_start_at'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                    'key' => null,
                ];
                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
