<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Dreaming;
use App\Helpers\_Evo888;
use App\Helpers\_FunkyGames;
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


class ProcessFunkyGameBetLog implements ShouldQueue
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
        $startDate = $date->copy()->subMinutes(30)->toIso8601String();
        $endDate = $date->copy()->toIso8601String();

        $betTickets = _FunkyGames::getBets($startDate, $endDate);
        $this->process($betTickets);
    }


    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winloss'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winloss'] == 0) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $adjustedBetTime = Carbon::parse($betTicket['betTime'])->addHours(8);
               
                $betDetail = [
                    'bet_id' => "FUNKYGAMES_" . $betTicket['refNo'],
                    'product' => "FUNKYGAMES",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['playerId'],
                    'stake' => $betTicket['betAmount'],
                    'valid_stake' => $betTicket['effectiveStake'],
                    'payout' => $betTicket['betAmount'] + $betTicket['winloss'],
                    'winlose' => $betTicket['winloss'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => $betTicket['statementDate'],
                    'round_at' => $adjustedBetTime,
                    'round_date' => $adjustedBetTime->format('Y-m-d'),
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
