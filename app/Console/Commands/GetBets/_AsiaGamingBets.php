<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_AsiaGaming;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

class _AsiaGamingBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_AsiaGamingBets {date?}';

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
        $startDate = $date->copy()->subHours(12)->subMinutes(10)->format('Y-m-d H:i:s');
        $betTickets = _AsiaGaming::getBets($startDate, $endDate);
        
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                echo "here".json_encode($betTicket);

                $winlose = $betTicket['netAmount'];

                if ($betTicket['netAmount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['netAmount'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                switch ($betTicket['flag']) {
                    case 0:
                        $bet_status = "UNKNOWN";
                        break;
                    case 1:
                        $bet_status = "SETTLED";
                        break;
                    case -8:
                        $bet_status = "CANCEL ROUND";
                        break;
                    case -9:
                        $bet_status = "CANCEL BET";
                        break;
                }

                $accountDate = new DateTime($betTicket['recalcuTime'], new DateTimeZone('UTC'));
                $roundAt =  new DateTime($betTicket['betTime'], new DateTimeZone('UTC'));
                $accountDate->setTimezone(new DateTimeZone('GMT+12'));
                $roundAt->setTimezone(new DateTimeZone('GMT+12'));
                $betDetail = [
                    'bet_id' => "AG_" . $betTicket['billNo'],
                    'product' => "AG",
                    'game' => $betTicket['gameCode'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['playName'],
                    'stake' => $betTicket['betAmount'],
                    'valid_stake' => abs($betTicket['validBetAmount']),
                    'payout' => $betTicket['netAmount'] + $betTicket['betAmount'],
                    'winlose' => $betTicket['netAmount'],
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
        }
    }
}
