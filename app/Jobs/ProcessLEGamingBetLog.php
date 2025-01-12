<?php

namespace App\Jobs;

use App\Helpers\_LEGaming;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessLEGamingBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $argument;

    public function __construct($argument)
    {
        $this->argument = $argument;
    }

    public function handle()
    {
        $date = $this->argument ? Carbon::parse($this->argument) : now();
        $start_date = $date->copy()->subMinutes(60)->timestamp * 1000;
        $end_date = $date->copy()->timestamp * 1000; // GMT+0
        $betTickets = _LEGaming::getBets($start_date, $end_date);

        if (is_array($betTickets) && !empty($betTickets)) {
            $this->handleBetTickets($betTickets);
        }
    }

    public function transformData($originalData)
    {
        $transformedData = [];
        $recordCount = count($originalData['GameID']);

        for ($i = 0; $i < $recordCount; $i++) {
            $record = [];
            foreach ($originalData as $key => $values) {
                $record[$key] = $values[$i];
            }
            $transformedData[] = $record;
        }

        return $transformedData;
    }

    public function handleBetTickets($originalData)
    {
        $transformedData = $this->transformData($originalData);
        $this->process($transformedData);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                $stake = $betTicket['AllBet'];
                $valid_stake = $betTicket['CellScore'];
                $winlose = $betTicket['Profit'];
                $payout = $winlose + $valid_stake;

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose == 0) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => 'LEGAMING_' . $betTicket['GameID'],
                    'product' => "LEGAMING",
                    'game' => $betTicket['KindID'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => explode('_', $betTicket['Accounts'])[1],
                    'stake' => $stake,
                    'valid_stake' => $valid_stake,
                    'payout' => $payout,
                    'winlose' => $winlose,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['GameEndTime'])->format('Y-m-d'), // GMT+8
                    'round_at' => Carbon::parse($betTicket['GameStartTime'])->format('Y-m-d H:i:s'),
                    'round_date' => Carbon::parse($betTicket['GameStartTime'])->format('Y-m-d'),
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
