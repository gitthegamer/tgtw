<?php

namespace App\Jobs;

use App\Helpers\_3Win8;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_Dreaming;
use App\Helpers\_Evo888;
use App\Helpers\_King855;
use App\Helpers\_NextSpin;
use App\Helpers\_PGS;
use App\Helpers\_Vpower;
use App\Helpers\_WCasino;
use App\Helpers\_WMCasino;
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


class ProcessWCasinoBetLog implements ShouldQueue
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
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $date = $this->argument ? Carbon::parse($this->argument) : now();
        //start date is 15min before and end date is now
        $start_date = $date->copy()->subMinutes(60)->timestamp;
        $end_date = $date->copy()->timestamp;

        $betTickets = _WCasino::getBets($start_date, $end_date);
        $this->process($betTickets);
    }


    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['winlost'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['winlost'] == 0) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "";
                switch ($betTicket['state']) {
                    case '0':
                        $bet_status = "WAITING";
                        break;
                    case '1':
                        $bet_status = "SETTLED";
                        break;
                    case '-2':
                        $bet_status = "CANCELLED";
                        break;
                    case '-1':
                        $bet_status = "REJECTED";
                        break;
                    case '2':
                        $bet_status = "SETTLED";
                        break;
                    case '-4':
                        $bet_status = "INTERNET ABNORMAL";
                        break;
                    case '-3':
                        $bet_status = "ERROR";
                        break;
                    default:
                        $bet_status = "WAITING";
                        break;
                }

                $gameType = "";
                switch ($betTicket['gametype']) {
                    case '4':
                        $gameType = "BACCARAT";
                        break;
                    case '5':
                        $gameType = "ROULETTE";
                        break;
                    case '11':
                        $gameType = "SICBO";
                        break;
                    case '19':
                        $gameType = "FISH PRAWN CRAB";
                        break;
                    case '18':
                        $gameType = "COLOR DISC";
                        break;
                    case '10':
                        $gameType = "DRAGON TIGER";
                        break;
                    default:
                        $gameType = "OTHER";
                        break;
                }

                $payout = $betTicket['winlost'] + $betTicket['betamount'];
                $payout = $payout < 0 ? 0 : $payout;

                $betDetail = [
                    'bet_id' => "WCASINO_" . $betTicket['id'],
                    'product' => "WCASINO",
                    'game' => $gameType,
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['username'],
                    'stake' => $betTicket['betamount'],
                    'valid_stake' => $betTicket['commamount'],
                    'payout' => $payout,
                    'winlose' => $betTicket['winlost'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['updatetime']),
                    'round_at' => Carbon::parse($betTicket['createtime']),
                    'round_date' => Carbon::parse($betTicket['createtime'])->format('Y-m-d'),
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
