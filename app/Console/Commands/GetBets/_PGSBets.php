<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_918kiss;
use App\Helpers\_PGS;
use App\Helpers\_Vpower;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Console\Command;

class _PGSBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_PGSBets {date?}';

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
        $date = $date->setTimezone(new DateTimeZone("UTC"));
        $endDate = $date->format('Y-m-d H:i:s');
        $startDate = $date->subHour()->format('Y-m-d H:i:s');
        $betTickets = _PGS::getBets($startDate, $endDate, 1);
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets)) {
            $upserts = [];

            foreach ($betTickets as $betTicket) {
                if ($betTicket['winLossAmount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winLossAmount'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => "PGS_" . $betTicket['roundID'],
                    'product' => "PGS",
                    'game' => $betTicket['gameID'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['playerName'],
                    'stake' => $betTicket['betAmount'],
                    'valid_stake' => $betTicket['betAmount'],
                    'payout' => $betTicket['payoutAmount'],
                    'winlose' => $betTicket['winLossAmount'],
                    'before_balance' => $betTicket['beforeAmount'],
                    'after_balance' => $betTicket['beforeAmount'] + $betTicket['winLossAmount'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['roundEndTime'])->addHours(8),
                    'round_at' => Carbon::parse($betTicket['roundStartTime'])->addHours(8),
                    'round_date' => Carbon::parse($betTicket['roundStartTime'])->addHours(8)->format('Y-m-d'),
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