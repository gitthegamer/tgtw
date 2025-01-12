<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Evo888;
use App\Helpers\_Lionking;
use App\Helpers\_Lucky365;
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


class ProcessLionKingBetLog implements ShouldQueue
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

        $date = $this->argument ? Carbon::parse($this->argument) : now();
        $endDate = $date->copy()->format('Y-m-d H:i:s');
        $startDate = $date->copy();
        $startDate = $startDate->subMinutes(30)->format('Y-m-d H:i:s');
        $betTickets = _Lionking::getBets($startDate, $endDate);
        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['validWin'] - $betTicket['validBet'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['validWin'] - $betTicket['validBet']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED"; 

                $betDetail = [
                    'bet_id' => 'LK_'.$betTicket['orderCode'],
                    'product' => 'LK',
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['loginId'],
                    'stake' => $betTicket['validBet'],
                    'valid_stake' => $betTicket['validBet'],
                    'payout' => $betTicket['validWin'], 
                    'winlose' => $betTicket['validWin'] - $betTicket['validBet'], 
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['actionDate']),
                    'round_at' => Carbon::parse($betTicket['actionDate']),
                    'round_date' => Carbon::parse($betTicket['actionDate'])->format('Y-m-d'),
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
