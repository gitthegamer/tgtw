<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Evo888;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessVpowerDailyBetLog implements ShouldQueue
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
        $endDate = $date->copy()->endOfDay()->format('Y-m-d H:i:s');
        $startDate = $date->copy()->startOfDay()->format('Y-m-d H:i:s');
        
        $betTickets = _Vpower::getBets($startDate, $endDate);
        SELF::process($betTickets);
    }

    public static function process($betTickets)
    {
        if ($betTickets && count($betTickets)) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {


                if (floatval($betTicket['win']) -  floatval($betTicket['pay']) > 0) {
                    $payout_status = "WIN";
                } elseif (floatval($betTicket['win']) -  floatval($betTicket['pay']) < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }
                $bet_status = "SETTLED";

                $before_balance = (float) $betTicket['startscore'];
                $after_balance = (float) $betTicket['finalscore'];

                $betDetail = [
                    'bet_id' => 'VP_' . $betTicket['uniqleid'],
                    'product' => "VP",
                    'game' => $betTicket['gameocode'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['username'],
                    'stake' => floatval($betTicket['pay']),
                    'valid_stake' => floatval($betTicket['pay']),
                    'payout' => floatval($betTicket['win']),
                    'winlose' => floatval($betTicket['win']) -  floatval($betTicket['pay']),
                    'before_balance' => $before_balance,
                    'after_balance' => $after_balance,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => date('Y-m-d H:i:s', $betTicket['time']),
                    'round_at' => date('Y-m-d H:i:s', $betTicket['time']),
                    'round_date' => Carbon::parse($betTicket['time'])->format('Y-m-d'),
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
