<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Dreaming;
use App\Helpers\_Evo888;
use App\Helpers\_King855;
use App\Helpers\_NextSpin;
use App\Helpers\_PGS;
use App\Helpers\_Vpower;
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
use DateTimeZone;


class ProcessNextSpinBetLog implements ShouldQueue
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
        $date = $this->argument ? Carbon::parse($this->argument) : now();
        //start date is 15min before and end date is now
        $start_date = $date->copy()->subMinutes(15)->format('Ymd\THis');
        $end_date = $date->copy()->format('Ymd\THis');

        $betTickets = _NextSpin::getBets($start_date, $end_date);
        $this->process($betTickets);
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
