<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_SunCity;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class _SunCityBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_SunCityBets {date?}';

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
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now();
        $endDate = $date->copy()->subMinutes(10)->format('Y-m-d H:i:s');
        $startDate = $date->copy()->subMinutes(60)->format('Y-m-d H:i:s');

        //if startdate date and end date date is not the same
        if (Carbon::parse($endDate)->format('Y-m-d') != Carbon::parse($startDate)->format('Y-m-d')) {
            $endDate = Carbon::parse($startDate)->endOfDay()->format('Y-m-d H:i:s');
        }

        $betTickets = _SunCity::getBets($startDate, $endDate, 1);
        $this->process($betTickets);

        return 0;
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
                    'bet_id' => 'SUNCITY_' . $betTicket['GameSerialID'],
                    'product' => "SUNCITY",
                    'game' => $betTicket['ThemeID'],
                    'category' => Product::CATEGORY_SLOTS,
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
