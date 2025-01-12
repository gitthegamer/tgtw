<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_LEGaming;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _LEGamingBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:LEGamingBets {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->copy();
        $start_date = $date->copy()->subMinutes(60)->timestamp * 1000;
        $end_date = $date->copy()->timestamp * 1000; // GMT+0

        $betTickets = _LEGaming::getBets($start_date, $end_date);

        if (is_array($betTickets) && !empty($betTickets)) {
            $this->handleBetTickets($betTickets);
        }

        return 0;
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
