<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_CQ9;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;

class _CQ9Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_CQ9Bets {date?}';

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

        $endtime = $date->copy()->setTimezone(new \DateTimeZone('-04:00'))->format('Y-m-d\TH:i:sP');
        $starttime = $date->copy()->subHours(1)->setTimezone(new \DateTimeZone('-04:00'))->format('Y-m-d\TH:i:sP');

        $betTickets = _CQ9::getBets($starttime, $endtime);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                $winlose = 0;
                $payout = 0;
                if ($betTicket['gametype'] == 'table') {
                    $winlose = $betTicket['win'] - $betTicket['rake'] + $betTicket['roomfee'];
                    $payout = $betTicket['win'] + $betTicket['bet'] - $betTicket['rake'] + $betTicket['roomfee'];
                } else {
                    $winlose = $betTicket['win'] - $betTicket['bet'];
                    $payout = $betTicket['win'];
                }

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if ($betTicket['status'] == 'complete') {
                    $bet_status = "SETTLED";
                } else {
                    $bet_status = "UNKNOWN";
                }

                $accountDate = new DateTime($betTicket['endroundtime'], new DateTimeZone('UTC'));
                $roundAt =  new DateTime($betTicket['bettime'], new DateTimeZone('UTC'));

                $accountDate->setTimezone(new DateTimeZone('GMT+12'));
                $roundAt->setTimezone(new DateTimeZone('GMT+8'));

                switch ($betTicket['gametype']) {
                    case 'table':
                        $category = Product::CATEGORY_TABLE;
                        $validstake = $betTicket['validbet'];
                        break;
                    case 'slot':
                        $category = Product::CATEGORY_SLOTS;
                        $validstake = $betTicket['bet'];
                        break;
                    case 'fish':
                        $category = Product::CATEGORY_FISH;
                        $validstake = $betTicket['bet'];
                        break;
                    case 'arcade':
                        $category = Product::CATEGORY_FISH;
                        $validstake = $betTicket['bet'];
                        break;
                }

                $betDetail = [
                    'bet_id' => "CQ9_" . $betTicket['round'],
                    'product' => "CQ9",
                    'game' => $betTicket['gamecode'],
                    'category' => $category,
                    'username' => $betTicket['account'],
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $validstake,
                    'payout' => $payout,
                    'winlose' => $winlose,
                    'jackpot_win' => $betTicket['jackpot'],
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
