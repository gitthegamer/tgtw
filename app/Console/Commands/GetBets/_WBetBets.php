<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Playboy;
use App\Models\BetLog;
use App\Models\Product;
use App\Modules\_WBetController;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _WBetBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_WBetBets';

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
        $betTickets = _Playboy::getBets();
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            $ids = [];
            foreach ($betTickets as $betTicket) {

                $winlose = $betTicket['payout'] - $betTicket['bet'];

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                switch ($betTicket['status']) {
                    case 1:
                        $bet_status = "SETTLED";
                        break;
                    case 0:
                        $bet_status = "RUNNING";
                        break;
                    case -1:
                        $bet_status = "CANCELLED";
                        break;
                    default:
                        $bet_status = "UNKNOWN";
                        break;
                }


                $betDetail = [
                    'bet_id' => 'WBET_' . $betTicket['id'],
                    'product' => "WBET",
                    'game' => $betTicket['game_id'],
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => $betTicket['member'],
                    'stake' => $betTicket['bet'],
                    'valid_stake' => $betTicket['turnover'],
                    'payout' => $betTicket['payout'],
                    'winlose' => $winlose,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['end_time'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['start_time'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['start_time'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];

                $ids[] = $betTicket['id'];
                $upserts[] = $betDetail;
            }

            $ticket = '';
            foreach ($ids as $item) {
                $ticket .= $item . ', ';
            }
            $ticket = rtrim($ticket, ', ');
            if ($ticket != '') {
                $response = _WBetController::init("markbyjson.aspx", [
                    "operatorcode" => config('api.PLAYBOY_OPERATOR_CODE'),
                    'ticket' => $ticket,
                    'providercode' => config('api.PLAYBOY_PROVIDER_CODE'),
                ]);
            }


            BetLog::upsertByChunk($upserts);
        }
    }
}
