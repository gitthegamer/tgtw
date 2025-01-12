<?php

namespace App\Jobs;

use App\Helpers\_ACE333;
use App\Helpers\_Sexybrct;

use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Setting;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessAce333BetLog implements ShouldQueue
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

        $date = $this->argument ? Carbon::parse($this->argument)->setTimezone('UTC') : Carbon::now()->setTimezone('UTC');
        //dataString is start time and maximum is 1 hour
        $dateString = $date->copy()->subMinutes(15)->format('Y-m-d H:i:s.v');

        $betTickets = _ACE333::getBets($dateString);
        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if (!empty($betTickets)) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                if ($betTicket['methodType'] !== "BR") {
                    continue;
                }
                $win = $betTicket['winAmount'];
                $bet = $betTicket['betAmount'];
                $status = $betTicket['status'];

                if (($win - $bet) > 0) {
                    $payout_status = "WIN";
                } elseif ($win == $bet) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }

                if ($status == "C") {
                    $bet_status = "SETTLED";
                } else {
                    $bet_status = "PENDING";
                }

                $memberAccount = Cache::remember(
                    'member_account.' . $betTicket['playerID'] . ".ACE333",
                    60 * 60 * 24,
                    function () use ($betTicket) {
                        return MemberAccount::whereHas('product', function ($q) use ($betTicket) {
                            $q->where('code', 'ACE333');
                        })->where('account', $betTicket['playerID'])->first();
                    }
                );


                $betDetail = [
                    'bet_id' => "ACE333_" . $betTicket['referenceID'],
                    'product' => "ACE333",
                    'game' => $betTicket['gameID'], //game code on Provider system
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $memberAccount->username, //player ID in Operator system
                    'stake' => $bet,
                    'valid_stake' => $bet,
                    'payout' => $win,
                    'winlose' => $win - $bet,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['updated'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['created'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['created'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
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
