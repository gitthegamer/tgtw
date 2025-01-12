<?php

namespace App\Jobs;

use App\Helpers\_AstarCasino;
use App\Helpers\_CQ9;
use App\Models\BetLog;
use DateTime;
use DateTimeZone;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAstarBetLog implements ShouldQueue
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

        $start_date = $date->copy()->subMinutes(60)->format('Y-m-d H:i:s');
        $end_date = $date->copy()->format('Y-m-d H:i:s');

        $betTickets = _AstarCasino::getBets($start_date, $end_date);
        $this->process($betTickets);
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {
                if ($betTicket['money'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['money'] == 0) {
                    $payout_status = "DRAW";
                } else {
                    $payout_status = "LOSE";
                }
                $bet_status = "";

                switch ($betTicket['status']) {
                    case "A":
                        $bet_status = "SETTLED";
                        break;
                    case "B":
                        $bet_status = "ABNORMAL";
                        break;
                    case "C":
                        $bet_status = "CANCELLED";
                        break;
                    default:
                        $bet_status = "WAITING";
                        break;
                }

                $gameType = "";
                if (array_key_exists('gameType', $betTicket)) {
                    switch ($betTicket['gameType']) {
                        case 1:
                            $gameType = "BACCARAT";
                            break;
                        case 2:
                            $gameType = "DRAGON TIGER";
                            break;
                        case 3:
                            $gameType = "ROULETTE";
                            break;
                        case 4:
                            $gameType = "SIC BO";
                            break;
                        case 5:
                            $gameType = "BULL BULL";
                            break;
                        default:
                            $gameType = "";
                            break;
                    }
                }

                $playType = "";
                switch ($betTicket['playType']) {
                    case 1:
                        $playType = "LIVEVIDEO_";
                        break;
                    case 2:
                        $playType = "LIVEBROADCAST_";
                        break;
                    case 3:
                        $playType = "HASH_";
                        break;
                    case 4:
                        $playType = "CHESS_";
                        break;
                    default:
                        $playType = "";
                        break;
                }

                $betDetail = [
                    'bet_id' => "ASTARCASINO_" . $betTicket['id'],
                    'product' => "ASTARCASINO",
                    'game' => $playType . $gameType,
                    'category' => Product::CATEGORY_LIVE,
                    'username' => str_replace(config('api.ASTAR_CHANNEL'), '', $betTicket['userName']),
                    'stake' => $betTicket['betAmount'],
                    'valid_stake' => $betTicket['validBetAmount'],
                    'payout' => $betTicket['money'] + $betTicket['betAmount'],
                    'winlose' => $betTicket['money'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['endTime'], 'Asia/Shanghai')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_at' => Carbon::parse($betTicket['createTime'], 'Asia/Shanghai')->setTimezone('Asia/Kuala_Lumpur'),
                    'round_date' => Carbon::parse($betTicket['createTime'])->format('Y-m-d'),
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
