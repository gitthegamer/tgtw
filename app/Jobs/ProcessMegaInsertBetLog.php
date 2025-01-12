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

class ProcessMegaInsertBetLog implements ShouldQueue
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

                if ($betTicket['win'] > $betTicket['bet']) {
                    $payout_status = "WIN";
                } elseif ($betTicket['win'] == $betTicket['bet']) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => $this->member . "_MG_" . $betTicket['id'],
                    'product' => "MG",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_APP,
                    'username' => $this->member,
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $betTicket['bet'],
                    'payout' => $betTicket['win'],
                    'winlose' => $betTicket['win'] - $betTicket['bet'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['createTime']),
                    'round_at' => Carbon::parse($betTicket['createTime']),
                    'round_date' => Carbon::parse($betTicket['createTime'])->format('Y-m-d'),
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
