<?php

namespace App\Jobs;

use App\Helpers\_Playboy;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPlayboyBetLog implements ShouldQueue
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
        $endDate = $date->copy()->subMinutes(10)->format('Y-m-d H:i:s');
        $startDate = $date->copy()->subMinutes(60)->format('Y-m-d H:i:s');

        //if startdate date and end date date is not the same
        if (Carbon::parse($endDate)->format('Y-m-d') != Carbon::parse($startDate)->format('Y-m-d')) {
            $endDate = Carbon::parse($startDate)->endOfDay()->format('Y-m-d H:i:s');
        }

        $betTickets = _Playboy::getBets($startDate, $endDate, 1);
        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                if ($betTicket['WinAmount'] - $betTicket['BetCoin'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['WinAmount'] - $betTicket['BetCoin']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => 'PLAYBOY_' . $betTicket['GameNO'],
                    'product' => "PLAYBOY",
                    'game' => $betTicket['ThemeID'],
                    'category' => Product::CATEGORY_APP,
                    'username' => strtoupper($betTicket['UserName']),
                    'stake' => $betTicket['BetCoin'],
                    'valid_stake' => $betTicket['BetCoin'],
                    'payout' => $betTicket['WinAmount'],
                    'winlose' => $betTicket['WinAmount'] - $betTicket['BetCoin'], // if cant , using AvailTotalWin-AvailTotalBet
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

            BetLog::upsertByChunk($upserts);
        }
    }
}
