<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_M8;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class _M8Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_M8Bets';

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
        $betTickets = _M8::getBets();
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            $ticketIds = [];
            foreach ($betTickets as $betTicket) {
                $payout_status = "WAITING";
                $bet_status = "WAITING";
                switch ($betTicket['res']) {
                    case 'WA':
                        $payout_status = "WINALL";
                        $bet_status = "SETTLED";
                        break;
                    case 'P':
                        $payout_status = "NOTMATCHOVER";
                        $bet_status = "NOTMATCHOVER";
                        break;
                    case 'LA':
                        $payout_status = "LOSTALL";
                        $bet_status = "SETTLED";
                        break;
                    case 'WH':
                        $payout_status = "WINHALF";
                        $bet_status = "SETTLED";
                        break;
                    case 'LH':
                        $payout_status = "LOSTHALF";
                        $bet_status = "SETTLED";
                        break;
                    case 'D':
                        $payout_status = "DRAW";
                        $bet_status = "SETTLED";
                        break;
                    default:
                        $payout_status = "WAITING";
                        $bet_status = "WAITING";
                        break;
                }

                $betDetail = [
                    'bet_id' => "M8_" . $betTicket['id'],
                    'product' => "M8",
                    'game' => $betTicket['game'],
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => $betTicket['u'],
                    'stake' => $betTicket['b'],
                    'valid_stake' => $betTicket['b'],
                    'payout' => $betTicket['b'] + $betTicket['w'],
                    'winlose' => $betTicket['w'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['workdate'])->format('Y-m-d'),
                    'round_at' => Carbon::parse($betTicket['matchdatetime']),
                    'round_date' => Carbon::parse($betTicket['matchdatetime'])->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];
                $upserts[] = $betDetail;
                $ticketIds[] = $betTicket['fid'];
            }

            BetLog::upsertByChunk($upserts);
            if(count($ticketIds) != 0){
                _M8::updateBets(implode(',', $ticketIds));
            }
        }
    }
}