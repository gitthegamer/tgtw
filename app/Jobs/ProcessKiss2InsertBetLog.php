<?php

namespace App\Jobs;

use App\Models\BetLog;
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

class ProcessKiss2InsertBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $betTickets;
    protected $member;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $betTickets, $member)
    {
        $this->betTickets = $betTickets;
        $this->member = $member;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        if ($this->betTickets && count($this->betTickets)) {
            $upserts = [];

            foreach ($this->betTickets as $betTicket) {

                $win = (float) $betTicket['win'];
                $bet = (float) $betTicket['bet'];

                if (($win - $bet) > 0) {
                    $payout_status = "WIN";
                } elseif ($win == $bet) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => $this->member . "_9K2_" . $betTicket['id'],
                    'product' => "918KISS2",
                    'game' => $betTicket['game'],
                    'category' => Product::CATEGORY_APP,
                    'username' => $this->member,
                    'stake' => $bet,
                    'valid_stake' => $bet,
                    'payout' => $win,
                    'winlose' => $win - $bet,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['date_time']),
                    'round_at' => Carbon::parse($betTicket['date_time']),
                    'round_date' => Carbon::parse($betTicket['date_time'])->format('Y-m-d'),
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
