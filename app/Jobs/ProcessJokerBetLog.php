<?php

namespace App\Jobs;

use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_AWC;
use App\Helpers\_Evo888;
use App\Helpers\_Playboy;
use App\Helpers\Joker;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Modules\_PlayboyController;
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

class ProcessJokerBetLog implements ShouldQueue
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
        $betTickets = Joker::getBets($date, true);
        $this->process($betTickets);
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
                    'before_balance' => $betTicket['StartBalance'],
                    'after_balance' => $betTicket['EndBalance'],
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
