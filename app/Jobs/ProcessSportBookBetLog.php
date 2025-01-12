<?php

namespace App\Jobs;

use App\Helpers\_Sportsbook;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DateTimeZone;


class ProcessSportBookBetLog implements ShouldQueue
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
        $interval = new DateInterval('PT30M');
        $endDate = $date->format('Y-m-d\TH:i:sP');
        $startDate = $date;
        $startDate = $startDate->sub($interval)->format('Y-m-d\TH:i:sP');

        $betTickets = _Sportsbook::getBets($startDate, $endDate);
        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {

            $upserts = [];
            foreach ($betTickets as $betTicket) {

                $roundAt =  new DateTime($betTicket['orderTime'], new DateTimeZone('GMT-4'));
                $roundAt->setTimezone(new DateTimeZone('Asia/Shanghai'));

                $betDetail = [
                    'bet_id' => "SB_" . $betTicket['refNo'],
                    'product' => "SB",
                    'game' => $betTicket['sportsType'],
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => $betTicket['username'],
                    'stake' => $betTicket['stake'],
                    'valid_stake' => $betTicket['turnover'],
                    'payout' => $betTicket['stake'] + $betTicket['winLost'],
                    'winlose' => $betTicket['winLost'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => strtoupper($betTicket['status']),
                    'bet_status' => ($betTicket['status'] == 'draw' || $betTicket['status'] == 'lose' || $betTicket['status'] == 'won') ? "SETTLED" : 'NOT SETTLED',
                    'account_date' => Carbon::parse($betTicket['winLostDate'])->format('Y-m-d'),
                    'round_at' => $roundAt->format('Y-m-d H:i:s'),
                    'round_date' => $roundAt->format('Y-m-d'),
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
