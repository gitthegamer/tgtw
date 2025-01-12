<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Evo888;
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


class ProcessPgsBetLog implements ShouldQueue
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
        $date = $date->setTimezone(new DateTimeZone("UTC"));
        $endDate = $date->format('Y-m-d H:i:s');
        $startDate = $date->subHour()->format('Y-m-d H:i:s');
        $betTickets = _PGS::getBets($startDate, $endDate, 1);
        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets)) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                if ($betTicket['winLossAmount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winLossAmount'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => "PGS_" . $betTicket['roundID'],
                    'product' => "PGS",
                    'game' => $betTicket['gameID'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['playerName'],
                    'stake' => $betTicket['betAmount'],
                    'valid_stake' => $betTicket['betAmount'],
                    'payout' => $betTicket['payoutAmount'],
                    'winlose' => $betTicket['winLossAmount'],
                    'before_balance' => $betTicket['beforeAmount'],
                    'after_balance' => $betTicket['beforeAmount'] + $betTicket['winLossAmount'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['roundEndTime'])->addHours(8),
                    'round_at' => Carbon::parse($betTicket['roundStartTime'])->addHours(8),
                    'round_date' => Carbon::parse($betTicket['roundEndTime'])->addHours(8)->format('Y-m-d'),
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
