<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_MegaH5;
use App\Models\Bet;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _MegaH5Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_MegaH5 {date?}';

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
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->subMinutes(60);
        //     $bets = _MegaH5::getBets($date);
        //     Log::debug($bets);

        //     if (!empty($bets)) {
        //         foreach ($bets as $bet) {
        //             $member = MemberAccount::where('username', $bet['PlayerId'])->first();

        //             if ($member) {
        //                 $betTickets = _MegaH5::checkBets($bet['PlayerId']);
        //                 log::debug(json_encode($betTickets));
        //                 if (!empty($betTickets)) {
        //                     $this->process($member, $betTickets);
        //                 }
        //             }
        //         }
        //     }
        // }

        $bets = _MegaH5::getBets($date);

        $collection = collect($bets);
        $playerIds = $collection->pluck('PlayerId')->unique();

        if (!empty($playerIds)) {
            foreach ($playerIds as $playerId) {
                $member = MemberAccount::where('username', $playerId)->first();
                if ($member) {
                    $betTickets = _MegaH5::checkBets($playerId);
                    log::debug(json_encode($betTickets));
                    if (!empty($betTickets)) {
                        $this->process($member, $betTickets);
                    }
                }
            }
        }
    }

    public function process($member, $betTickets)
    {
        echo json_encode($betTickets) . "\n";
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                if ($betTicket['Payout'] > $betTicket['Turnover']) {
                    $payout_status = "WIN";
                } elseif ($betTicket['Payout'] == $betTicket['Turnover']) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "SETTLED";

                $betDetail = [
                    'bet_id' => 'MEGAH5_' . $betTicket['TranId'],
                    'product' => "MEGAH5",
                    'game' => $betTicket['GameName'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $member->username,
                    'stake' => $betTicket['Turnover'],
                    'valid_stake' => $betTicket['ValidTurnover'],
                    'payout' => $betTicket['Payout'],
                    'winlose' => $betTicket['Payout'] - $betTicket['Turnover'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['TranDt'])->timezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['TranDt'])->timezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['TranDt'])->timezone('Asia/Kuala_Lumpur'),
                    'modified_at' => now(),
                    'modified_date' => now(),
                    'bet_detail' => json_encode($betTicket),
                ];

                $upserts[] = $betDetail;
            }
            BetLog::upsertByChunk($upserts);
        }
    }
}
