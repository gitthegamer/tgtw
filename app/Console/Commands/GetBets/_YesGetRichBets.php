<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_YesGetRich;
use App\Models\BetLog;
use App\Models\Product;
use App\Modules\_YesGetRichController;
use Carbon\Carbon;
use App\Models\MemberAccount;
use Illuminate\Console\Command;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _YesGetRichBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_YGRBets {date?}';

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
        $starttime = $date->copy()->subMinutes(60)->toIso8601String(); // Start of the day
        $endtime = now()->toIso8601String(); // Current time

        Log::debug("time sent out: starttime=" . $starttime . ", endtime=" . $endtime);

        $betTickets = _YesGetRich::getBets($starttime, $endtime);

        Log::debug("received bet ticket:" . json_encode($betTickets));

        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                echo json_encode($betTicket) . "\n";
                $stake = $betTicket['BetAmount'];
                $valid_stake = $betTicket['ValidAmount'];
                $winlose = $betTicket['PayoffAmount'];
                $payout = $betTicket['PayoffAmount'] + $betTicket['BetAmount'];

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if (empty($betTicket['PayoffTime'])) {
                    $bet_status = "PENDING";
                } elseif (!isset($betTicket['WagersId']) || !isset($betTicket['GameId'])) {
                    $bet_status = "UNKNOWN";
                } else {
                    $bet_status = "SETTLED";
                }


                $betDetail = [
                    'bet_id' => $betTicket['WagersId'],
                    'product' => "YGR",
                    'game' => $betTicket['GameId'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['Account'],
                    'stake' => $stake,
                    'valid_stake' => $valid_stake,
                    'payout' => $payout,
                    'winlose' => $winlose,
                    'jackpot_win' => $betTicket['Jackpot'],
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['SettlementTime'])->format('Y-m-d H:i:s'),
                    'round_at' => Carbon::parse($betTicket['WagersTime'])->format('Y-m-d H:i:s'),
                    'round_date' => Carbon::parse($betTicket['WagersTime'])->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                    'key' => null,
                ];
                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
