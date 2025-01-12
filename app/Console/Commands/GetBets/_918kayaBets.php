<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_918kaya;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

class _918kayaBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_918kayaBets {date?}';

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
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : Carbon::now();

        $betTickets = _918kaya::product_logs($date);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if (!empty($betTickets)) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                $win = $betTicket['payOut'];
                $bet = $betTicket['betAmount'];
                $validBet = $betTicket['validAmount'];
                $status = $betTicket['finished'];

                if (($win - $bet) > 0) {
                    $payout_status = "WIN";
                } elseif ($win == $bet) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }

                if ($status) {
                    $bet_status = "SETTLED";
                } else {
                    $bet_status = "PENDING";
                }

                $timestamp = $betTicket['betTime'] / 1000;
                $date = Carbon::createFromTimestamp($timestamp);

                $betDetail = [
                    'bet_id' => "918KAYA_" . $betTicket['betId'],
                    'product' => "918KAYA",
                    'game' => $betTicket['gid'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['account'],
                    'stake' => $bet / 10000,
                    'valid_stake' => $validBet / 10000,
                    'payout' => $win / 10000,
                    'winlose' => ($win - $bet) / 10000,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => $date,
                    'round_at' => $date,
                    'round_date' => $date->format('Y-m-d'),
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
