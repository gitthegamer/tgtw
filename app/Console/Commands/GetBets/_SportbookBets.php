<?php

namespace App\Console\Commands\GetBets;


use App\Helpers\_Sportsbook;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class _SportbookBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_SportbookBets {date?}';

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
        $interval = new DateInterval('PT30M');
        $endDate = $date->format('Y-m-d\TH:i:sP');
        $startDate = $date;
        $startDate = $startDate->sub($interval)->format('Y-m-d\TH:i:sP');

        $betTickets = _Sportsbook::getBets($startDate, $endDate);
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {

            $upserts = [];
            foreach ($betTickets as $betTicket) {

                $roundAt =  new DateTime($betTicket['orderTime'], new DateTimeZone('GMT-4'));
                $roundAt->setTimezone(new DateTimeZone('Asia/Shanghai'));

                $betDetail = [
                    'bet_id' => "SB_" . $betTicket['refNo'],
                    'product' => "SB",
                    'game' => $betTicket['sportsType'],
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => $betTicket['username'],
                    'stake' => $betTicket['stake'],
                    'valid_stake' => $betTicket['turnover'],
                    'payout' => $betTicket['stake'] + $betTicket['winLost'],
                    'winlose' => $betTicket['winLost'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => strtoupper($betTicket['status']),
                    'bet_status' => ($betTicket['status'] == 'draw' || $betTicket['status'] == 'lose' || $betTicket['status'] == 'won') ? "SETTLED" : strtoupper($betTicket['status']), // TODO: QUESTION
                    'account_date' => Carbon::parse($betTicket['winLostDate'])->format('Y-m-d'),
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
