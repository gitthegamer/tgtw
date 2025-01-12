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
use App\Helpers\_WMCasino;
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


class ProcessWMCasinoBetLog implements ShouldQueue
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
        $start_date = $date->copy()->subMinutes(60)->format('YmdHis');
        $end_date = $date->copy()->format('YmdHis');

        $betTickets = _WMCasino::getBets($start_date, $end_date);
        $this->process($betTickets);
    }


    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winLoss'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winLoss'] == 0) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => "WMCASINO_" . $betTicket['betId'],
                    'product' => "WMCASINO",
                    'game' => $betTicket['gname'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => strtoupper($betTicket['user']),
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $betTicket['validbet'],
                    'payout' => $betTicket['winLoss'] + $betTicket['bet'],
                    'winlose' => $betTicket['winLoss'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['settime']),
                    'round_at' => Carbon::parse($betTicket['betTime']),
                    'round_date' => Carbon::parse($betTicket['betTime'])->format('Y-m-d'),
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
