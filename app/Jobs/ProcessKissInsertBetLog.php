<?php

namespace App\Jobs;

use App\Helpers\_888king2;
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

class ProcessKissInsertBetLog implements ShouldQueue
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
            
                if ($betTicket['GameID'] > 0) {
                    $win = (float) $betTicket['Win'];
                    $bet = (float) $betTicket['bet'];

                    if (($win - $bet) > 0) {
                        $payout_status = "WIN";
                    } elseif ($win == $bet) {
                        $payout_status = "DRAW";
                    } else {
                        $payout_status = "LOSE";
                    }
                    $bet_status = "SETTLED";

                    $before_balance = (float) $betTicket['BeginBlance'];
                    $after_balance = (float) $betTicket['EndBlance'];


                    $betDetail = [
                        'bet_id' => $this->member . "_9K_" . $betTicket['uuid'],
                        'product' => "9K",
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
                        'account_date' => Carbon::parse($betTicket['CreateTime']),
                        'round_at' => Carbon::parse($betTicket['CreateTime']),
                        'round_date' => Carbon::parse($betTicket['CreateTime'])->format('Y-m-d'),
                        'modified_at' => now(),
                        'modified_date' => now()->format('Y-m-d'),
                        'bet_detail' => json_encode($betTicket),
                    ];

                    $upserts[] = $betDetail;
                }
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
