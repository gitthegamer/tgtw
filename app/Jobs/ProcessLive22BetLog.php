<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Evo888;
use App\Helpers\_Live22;
use App\Helpers\_PGS;
use App\Helpers\_Vpower;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Modules\_Live22Controller;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DateTimeZone;


class ProcessLive22BetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $argument;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($argument)
    {
        $this->argument = $argument;
        $this->queue = 'fetch_bet_logs';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $datetime = Carbon::now()->format('Y-m-d H:i:s');
        $betTickets =  _Live22::getBets($datetime);
        $this->process($betTickets);
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

                switch ($betTicket['GameType']) {
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

            $idChunks = array_chunk($ids, 1000);

            foreach ($idChunks as $chunk) {
                if (!empty($chunk)) {
                    $response = _Live22Controller::init("FlagLog", [
                        'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
                        'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'TransactionIds' => $chunk,
                    ]);
                }
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
