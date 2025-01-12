<?php

namespace App\Jobs;

use App\Helpers\_GW99;
use App\Helpers\BG;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DateTimeZone;


class ProcessGW99BetLog implements ShouldQueue
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
        $endDate = $date->copy()->format('Y-m-d H:i:s') . "." . substr(now()->copy()->format('u'), 0, 3);
        $startDate = $date->copy()->subMinutes(30)->format('Y-m-d H:i:s') . "." . substr(now()->copy()->format('u'), 0, 3);
        $betTickets = _GW99::getBets($startDate, $endDate, 1);
        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['availTotalWin'] - $betTicket['betCoin'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['availTotalWin'] - $betTicket['betCoin']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => 'GW99_' . $betTicket['gameSerialId'],
                    'product' => "GW99",
                    'game' => $betTicket['themeId'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['userName'],
                    'stake' => $betTicket['betCoin'],
                    'valid_stake' => $betTicket['betCoin'],
                    'payout' => $betTicket['availTotalWin'],
                    'winlose' => $betTicket['availTotalWin'] - $betTicket['betCoin'],
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
