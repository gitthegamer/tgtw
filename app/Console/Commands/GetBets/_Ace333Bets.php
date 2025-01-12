<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_ACE333;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _Ace333Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Ace333Bets {date?}';

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
        $date = $this->argument('date') ? Carbon::parse($this->argument('date'))->setTimezone('UTC') : Carbon::now()->setTimezone('UTC');
        //dataString is start time and maximum is 1 hour
        $dateString = $date->copy()->subMinutes(30)->format('Y-m-d H:i:s.v');

        $betTickets = _ACE333::getBets($dateString);
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if (!empty($betTickets)) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                if ($betTicket['methodType'] !== "BR") {
                    continue;
                }
                $win = $betTicket['winAmount'];
                $bet = $betTicket['betAmount'];
                $status = $betTicket['status'];

                if (($win - $bet) > 0) {
                    $payout_status = "WIN";
                } elseif ($win == $bet) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }

                if ($status == "C") {
                    $bet_status = "SETTLED";
                } else {
                    $bet_status = "PENDING";
                }

                $memberAccount = Cache::remember(
                    'member_account.' . $betTicket['playerID'] . ".ACE333",
                    60 * 60 * 24,
                    function () use ($betTicket) {
                        return MemberAccount::whereHas('product', function ($q) use ($betTicket) {
                            $q->where('code', 'ACE333');
                        })->where('account', $betTicket['playerID'])->first();
                    }
                );


                $betDetail = [
                    'bet_id' => "ACE333_" . $betTicket['referenceID'],
                    'product' => "ACE333",
                    'game' => $betTicket['gameID'], //game code on Provider system
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $memberAccount->username, //player ID in Operator system
                    'stake' => $bet,
                    'valid_stake' => $bet,
                    'payout' => $win,
                    'winlose' => $win - $bet,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['updated'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['created'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['created'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
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
