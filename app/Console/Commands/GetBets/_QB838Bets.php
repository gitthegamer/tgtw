<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_M8;
use App\Helpers\_QB838;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class _QB838Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_QB838Bets';

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
        $betTickets = _QB838::getBets();
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                $payout_status = "PENDING";
                $bet_status = "PENDING";
                switch ($betTicket['Status']) {
                    case '0':
                        $payout_status = "PENDING";
                        $bet_status = "PENDING";
                        break;
                    case '1':
                        $payout_status = "ACCEPTED";
                        $bet_status = "ACCEPTED";
                        break;
                    case '2':
                        $payout_status = "SETTLED";
                        $bet_status = "SETTLED";
                        break;
                    case '3':
                        $payout_status = "CANCELLED";
                        $bet_status = "CANCELLED";
                        break;
                    case '4':
                        $payout_status = "REJECTED";
                        $bet_status = "REJECTED";
                        break;
                    default:
                        $payout_status = "PENDING";
                        $bet_status = "PENDING";
                        break;
                }

                switch ($betTicket['Result']) {
                    case '0':
                        $payout_status = "DRAW";
                        $bet_status = $bet_status;
                        break;
                    case '1':
                        $payout_status = "WIN";
                        $bet_status = $bet_status;
                        break;
                    case '2':
                        $payout_status = "LOSE";
                        $bet_status = $bet_status;
                        break;
                    case '3':
                        $payout_status = "WIN HALF";
                        $bet_status = $bet_status;
                        break;
                    case '4':
                        $payout_status = "LOSE HALF";
                        $bet_status = $bet_status;
                        break;
                    default:
                        $payout_status = $payout_status;
                        $bet_status = $bet_status;
                        break;
                }

                $sports = NULL;
                switch ($betTicket['SubBets'][0]['SportID']) {
                    case '0':
                        $sports = "ALL SPORTS";
                        break;
                    case '1':
                        $sports = "SOCCER";
                        break;
                    case '2':
                        $sports = "BASKETBALL";
                        break;
                    case '3':
                        $sports = "AMERICAN FOOTBALL";
                        break;
                    case '4':
                        $sports = "BASEBALL";
                        break;
                    case '5':
                        $sports = "HOCKEY";
                        break;
                    case '6':
                        $sports = "LONG HOCKEY";
                        break;
                    case '7':
                        $sports = "TENNIS";
                        break;
                    case '8':
                        $sports = "BADMINTON";
                        break;
                    case '9':
                        $sports = "TABLE TENNIS";
                        break;
                    case '10':
                        $sports = "GOLF";
                        break;
                    case '11':
                        $sports = "CRICKET";
                        break;
                    case '12':
                        $sports = "VOLLEYBALL";
                        break;
                    case '13':
                        $sports = "HANDBALL";
                        break;
                    case '14':
                        $sports = "WATER POLO";
                        break;
                    case '15':
                        $sports = "BEACH VOLLEYBALL";
                        break;
                    case '16':
                        $sports = "INDOOR SOCCER";
                        break;
                    case '17':
                        $sports = "SNOOKER";
                        break;
                    case '18':
                        $sports = "FOOTBALL";
                        break;
                    case '19':
                        $sports = "RACING CAR";
                        break;
                    case '20':
                        $sports = "DARTS";
                        break;
                    case '21':
                        $sports = "BOXING";
                        break;
                    case '22':
                        $sports = "ATHLETICS";
                        break;
                    case '23':
                        $sports = "BICYCLE RACING";
                        break;
                    case '24':
                        $sports = "ENTERTAINMENT";
                        break;
                    case '25':
                        $sports = "WINTER SPORTS";
                        break;
                    case '26':
                        $sports = "E-SPORTS";
                        break;
                    default:
                        $sports = NULL;
                        break;
                }


                // Example:
                // Bet 100 
                // 赢50 
                // valid stake = 50

                // Bet 100 
                // 赢 300
                // Valid stake = 100

                // Bet 100 
                // 输80 
                // Valid stake = 80
                $winlose = $betTicket['Win'] ?? 0;
                $valid_stake = $betTicket['BetAmount'];
                if ($winlose > 0) {
                    if ($winlose >= $betTicket['BetAmount']) {
                        $valid_stake = $betTicket['BetAmount'];
                    } else {
                        $valid_stake = $winlose;
                    }
                } else if ($winlose < 0) {
                    $valid_stake = abs($winlose);
                }

                $betDetail = [
                    'bet_id' => "QB838_" . $betTicket['BetID'],
                    'product' => "QB838",
                    'game' => $sports,
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => strtoupper($betTicket['Account']),
                    'stake' => $betTicket['BetAmount'],
                    'valid_stake' => $valid_stake,
                    'payout' => $betTicket['Payout'],
                    'winlose' => $betTicket['Win'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['ReportDate'])->format('Y-m-d'),
                    'round_at' => Carbon::parse($betTicket['BetDate']),
                    'round_date' => Carbon::parse($betTicket['BetDate'])->format('Y-m-d'),
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