<?php

namespace App\Jobs;

use App\Helpers\_AdvantPlay;
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
use Illuminate\Support\Facades\Log;

class ProcessAdvantPlayDailyBetLog implements ShouldQueue
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
        $this->queue = 'fetch_bet_logs';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $date = $this->argument ? Carbon::parse($this->argument) : now()->copy();
        $endDate = $date->copy()->endOfDay()->format('Y-m-d H:i:s');
        $startDate = $date->copy()->startOfDay()->format('Y-m-d H:i:s');
        $betTickets = _AdvantPlay::getBets($startDate, $endDate);
        $this->process($betTickets);
    }

    // public function process($betTickets, $date)
    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['TotalWin'] - $betTicket['TotalStake'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['TotalWin'] - $betTicket['TotalStake'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if (strtolower($betTicket['StatusCode']) == 'settled') {
                    $bet_status = "SETTLED";
                } else {
                    $bet_status = $betTicket['StatusCode'];
                }

                $betDetail = [
                    'bet_id' => "AP_" . $betTicket['GameRoundId'],
                    'product' => "AP",
                    'game' => $betTicket['GameCode'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['PlayerId'],
                    'stake' => $betTicket['TotalStake'],
                    'valid_stake' => $betTicket['TotalStake'],
                    'payout' => $betTicket['TotalWin'],
                    'winlose' => $betTicket['TotalWin'] - $betTicket['TotalStake'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['SettleTime']),
                    'round_at' => Carbon::parse($betTicket['BetTime']),
                    'round_date' => Carbon::parse($betTicket['BetTime'])->format('Y-m-d'),
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
