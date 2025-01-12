<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Live22;
use App\Models\BetLog;
use App\Models\Product;
use App\Modules\_Live22Controller;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class _Live22Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Live22Bets';

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
        $betTickets =  _Live22::getBets();
        $this->process($betTickets);

        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            $ids = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['WinLose'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['WinLose']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                //TODO change Gametype and 1,2,3,0
                switch ($betTicket['GameType1']) {
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
                    'bet_id' => 'L2_' . $betTicket['TranId'],
                    'product' => "L2",
                    'game' => $betTicket['GameCode'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['PlayerId'],
                    'stake' => $betTicket['Turnover'],
                    'valid_stake' => $betTicket['ValidTurnover'],
                    'payout' => $betTicket['Payout'],
                    'winlose' => $betTicket['WinLose'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['TranDt'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['TranDt'], 'UTC')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['TranDt'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];

                $ids[] = $betTicket['TranId'];
                $upserts[] = $betDetail;
            }

            $ticket = implode(', ', $ids);

            if (!empty($ticket)) {
                $response = _Live22Controller::init("FlagLog", [
                    'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
                    'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                    'TransactionIds' => $ticket,
                ]);
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
