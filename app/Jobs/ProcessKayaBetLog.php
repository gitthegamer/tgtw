<?php

namespace App\Jobs;

use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Evo888;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessKayaBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $argument;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($argument)
    {
        $this->argument = $argument;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $date = $this->argument ? Carbon::parse($this->argument) : Carbon::now();

        $betTickets = _918kaya::product_logs($date);
        $this->process($betTickets);

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
