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

class ProcessPussyInsertBetLog implements ShouldQueue
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

        if ($this->betTickets && count($this->betTickets) > 0) {
            $upserts = [];

            foreach ($this->betTickets as $betTicket) {

                $win = (float) $betTicket['Win'];
                $bet = (float) $betTicket['bet'];

                $before_balance = (float) $betTicket['BeginBlance'];
                $after_balance = (float) $betTicket['EndBlance'];

                if (($win - $bet) > 0) {
                    $payout_status = "WIN";
                } elseif ($win == $bet) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => $this->member . "_PS_" . $betTicket['uuid'],
                    'product' => "PS",
                    'game' => $betTicket['GameName'],
                    'category' => Product::CATEGORY_APP,
                    'username' => $this->member,
                    'stake' => $bet,
                    'valid_stake' => $bet,
                    'payout' => $win,
                    'winlose' => $win - $bet,
                    'before_balance' => $before_balance,
                    'after_balance' => $after_balance,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['CreateTime'])->format('Y-m-d'),
                    'round_date' => Carbon::parse($betTicket['CreateTime'])->format('Y-m-d'),
                    'round_at' => $betTicket['CreateTime'],
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
