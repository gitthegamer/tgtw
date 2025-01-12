<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_WCasino;
use App\Helpers\Joker;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _WCasinoBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_WCasinoBets {date?}';

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
        //start date is 15min before and end date is now
        $start_date = $date->copy()->subMinutes(60)->timestamp;
        $end_date = $date->copy()->timestamp;

        $betTickets = _WCasino::getBets($start_date, $end_date);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winlost'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winlost'] == 0) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "";
                switch ($betTicket['state']) {
                    case '0':
                        $bet_status = "WAITING";
                        break;
                    case '1':
                        $bet_status = "SETTLED";
                        break;
                    case '-2':
                        $bet_status = "CANCELLED";
                        break;
                    case '-1':
                        $bet_status = "REJECTED";
                        break;
                    case '2':
                        $bet_status = "SETTLED";
                        break;
                    case '-4':
                        $bet_status = "INTERNET ABNORMAL";
                        break;
                    case '-3':
                        $bet_status = "ERROR";
                        break;
                    default:
                        $bet_status = "WAITING";
                        break;
                }

                $gameType = "";
                switch ($betTicket['gametype']) {
                    case '4':
                        $gameType = "BACCARAT";
                        break;
                    case '5':
                        $gameType = "ROULETTE";
                        break;
                    case '11':
                        $gameType = "SICBO";
                        break;
                    case '19':
                        $gameType = "FISH PRAWN CRAB";
                        break;
                    case '18':
                        $gameType = "COLOR DISC";
                        break;
                    case '10':
                        $gameType = "DRAGON TIGER";
                        break;
                    default:
                        $gameType = "OTHER";
                        break;
                }

                $payout = $betTicket['winlost'] + $betTicket['betamount'];
                $payout = $payout < 0 ? 0 : $payout;

                $betDetail = [
                    'bet_id' => "WCASINO_" . $betTicket['id'],
                    'product' => "WCASINO",
                    'game' => $gameType,
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['username'],
                    'stake' => $betTicket['betamount'],
                    'valid_stake' => $betTicket['commamount'],
                    'payout' => $payout,
                    'winlose' => $betTicket['winlost'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['updatetime']),
                    'round_at' => Carbon::parse($betTicket['createtime']),
                    'round_date' => Carbon::parse($betTicket['createtime'])->format('Y-m-d'),
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
