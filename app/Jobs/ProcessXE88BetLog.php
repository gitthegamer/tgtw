<?php

namespace App\Jobs;

use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_AWC;
use App\Helpers\_Evo888;
use App\Helpers\_Playboy;
use App\Helpers\_XE88;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Modules\_PlayboyController;
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

class ProcessXE88BetLog implements ShouldQueue
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
        $startDate = $date->copy()->format('Y-m-d');
        $startTime = $date->copy()->subMinutes(15)->format('HH:mm:ss');
        $endTime = $date->copy()->format('HH:mm:ss');

        $betTickets = _XE88::getBets($startDate, $startTime, $endTime);
        SELF::process($betTickets);
    }

    public static function process($betTickets)
    {
        if ($betTickets && count($betTickets)) {
            $upserts = [];
            
            foreach ($betTickets as $betTicket) { 
              
                
                if (($betTicket['win'] - $betTicket['bet']) > 0) {
                    $payout_status = "WIN";
                } elseif (($betTicket['win'] - $betTicket['bet']) < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => 'XE88_'.$betTicket['id'],
                    'product' => "XE88",
                    'game' => $betTicket['gamename'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['username'],
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $betTicket['bet'],
                    'payout' => $betTicket['win'],
                    'winlose' => $betTicket['win'] - $betTicket['bet'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => date('Y-m-d H:i:s', strtotime($betTicket['logtime'])),
                    'round_at' => date('Y-m-d H:i:s', strtotime($betTicket['logtime'])),
                    'round_date' => date('Y-m-d H:i:s', strtotime($betTicket['logtime']))->format('Y-m-d'),
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
