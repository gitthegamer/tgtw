<?php

namespace App\Jobs;

use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckMGgameLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $username;

    /**
     * The maximum number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2; // Change this to your desired retry limit


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($username)
    {
        $this->username = $username;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $date = now()->copy();
        $date = $date->format('Y-m-d H:i:s');

        $member = MemberAccount::where('username', $this->username)->first();
        if ($member) {

            try {
                $betTickets = Mega888::getDailyBets($this->username, $date, 1);
                $this->process($member, $betTickets);
            } catch (Exception $e) {
                $this->release(120); 
            }


        }

    }

    public function process($member, $betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                if ($betTicket['win'] > $betTicket['bet']) {
                    $payout_status = "WIN";
                } elseif ($betTicket['win'] == $betTicket['bet']) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => $member->username . "_MG_" . $betTicket['id'],
                    'product' => "MG",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_APP,
                    'username' => $member->username,
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
