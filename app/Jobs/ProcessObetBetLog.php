<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Dreaming;
use App\Helpers\_Evo888;
use App\Helpers\_King855;
use App\Helpers\_Obet33;
use App\Helpers\_PGS;
use App\Helpers\_Vpower;
use App\Helpers\IBC;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DateTimeZone;


class ProcessObetBetLog implements ShouldQueue
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

        $date = $this->argument ? Carbon::parse($this->argument) : now()->copy();
        $endDate = $date->format('YmdHis');
        $startDate = $date->subHour()->format('YmdHis');
        $betTickets = _Obet33::getBets($startDate, $endDate);
        $this->process($betTickets);

    }


    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                switch ($betTicket['Status']) {
                    case "-1":
                        $payout_status = "PENDING";
                        break;
                    case "0":
                        $payout_status = "ACCEPTED";
                        break;
                    case "1":
                        $payout_status = "REJECTED";
                        break;
                    case "4":
                        $payout_status = "CANCELLED";
                        break;
                    case "5":
                        $payout_status = "REFUNDED";
                        break;
                    default:
                        $payout_status = "WAITING";
                        break;
                }

                switch ($betTicket['Tresult']) {
                    case "0":
                        // $bet_status = "LOSE";
                        $bet_status = "SETTLED";
                        break;
                    case "1":
                        // $bet_status = "WIN";
                        $bet_status = "SETTLED";
                        break;
                    case "2":
                        // $bet_status = "DRAW";
                        $bet_status = "SETTLED";
                        break;
                    case "3":
                        // $bet_status = "WIN HALF";
                        $bet_status = "SETTLED";
                        break;
                    case "4":
                        // $bet_status = "LOSE HALF";
                        $bet_status = "SETTLED";
                        break;
                    default:
                        // $bet_status = "WAITING";
                        $bet_status = "WAITING";
                }

                $winlose = floatval($betTicket['Wamt'] ?? 0);
                $valid_stake = floatval($betTicket['Bamt']);
                if ($winlose > 0) {
                    if ($winlose >= floatval($betTicket['Bamt'])) {
                        $valid_stake = floatval($betTicket['Bamt']);
                    } else {
                        $valid_stake = $winlose;
                    }
                } else if ($winlose < 0) {
                    $valid_stake = abs($winlose);
                }


                $betDetail = [
                    'bet_id' => "OB_" . $betTicket['Id'],
                    'product' => "OB",
                    'game' => $betTicket['Sport'],
                    'category' => Product::CATEGORY_SPORTS,
                    'username' => $betTicket['User'],
                    'stake' => $betTicket['Bamt'],
                    'valid_stake' => $valid_stake,
                    'payout' => floatval($betTicket['Bamt']) + $betTicket['Wamt'] ?? 0,
                    'winlose' => floatval($betTicket['Wamt'] ?? 0),
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['SDate'])->format('Y-m-d'),
                    'round_at' => Carbon::parse($betTicket['ADate']),
                    'round_date' => Carbon::parse($betTicket['ADate'])->format('Y-m-d'),
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
