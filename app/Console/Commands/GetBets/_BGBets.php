<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\BG;
use App\Jobs\ProcessBGBetDetail;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers;

class _BGBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_BGBets {date?}';

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
        $endDate = $date->copy()->subHours(12)->format('Y-m-d H:i:s'); 
        $startDate = $date->copy()->subHours(12)->subDay()->format('Y-m-d H:i:s');

        $betTickets = BG::getBets($startDate, $endDate);
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if($betTicket['aAmount'] === null ){
                    continue;
                }

                if ($betTicket['payment'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['payment'] < 0) {
                    $payout_status = "LOSE";
                }else{
                    $payout_status = "DRAW";
                }
                
                switch ($betTicket['orderStatus']) {
                    case 0:
                        $bet_status = "BET NOT EXISTS";
                        break;
                    case 1:
                        $bet_status = "NOT SETTLED";
                        break;
                    case 2:
                        $bet_status = "SETTLED";
                        break;
                    case 3:
                        $bet_status = "SETTLED";
                        break;
                    case 4:
                        $bet_status = "SETTLED";
                        break;
                    case 5:
                        $bet_status = "CANCEL";
                        break;
                    case 6:
                        $bet_status = "EXPIRES";
                        break;
                    case 7:
                        $bet_status = "SYSTEM CANCEL";
                        break;
                    default:
                        $bet_status = "UNKNOWN";
                        break;
                }

                $accountDate = new DateTime($betTicket['lastUpdateTime'], new DateTimeZone('UTC'));
                $roundAt =  new DateTime($betTicket['orderTime'], new DateTimeZone('UTC'));
                $accountDate->setTimezone(new DateTimeZone('GMT+12'));
                $roundAt->setTimezone(new DateTimeZone('GMT+12'));
                $betDetail = [
                    'bet_id' => "BG_" . $betTicket['orderId'],
                    'product' => "BG",
                    'game' => $betTicket['gameName'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['loginId'],
                    'stake' => abs($betTicket['bAmount']),
                    'valid_stake' => abs($betTicket['bAmount']),
                    'payout' => $betTicket['aAmount'],
                    'winlose' => $betTicket['payment'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => $accountDate->format('Y-m-d H:i:s'),
                    'round_at' => $roundAt->format('Y-m-d H:i:s'),
                    'round_date' => $roundAt->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];
                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);

            foreach ($betTickets as $betTicket) {
                ProcessBGBetDetail::dispatch($betTicket['orderId']);
            }
        }
    }
}