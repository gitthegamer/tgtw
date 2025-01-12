<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_King855;
use App\Helpers\_Sexybrct;
use App\Http\Helpers;
use App\Jobs\ProcessBGBetDetail;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _King855Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_King855Bets';

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
        //PLEASE LA DIU
        $betTickets = _King855::getBets();
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            $ticketIds = [];
            foreach ($betTickets as $betTicket) {
                if (($betTicket['winOrLoss'] - $betTicket['betPoints']) > 0) {
                    $payout_status = "WIN";
                } elseif (($betTicket['winOrLoss'] - $betTicket['betPoints']) < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if ($betTicket['isRevocation'] == 1) {
                    $bet_status = "SETTLED";
                } elseif ($betTicket['isRevocation'] == 2) {
                    $bet_status = "REVOKED";
                } else {
                    $bet_status = "FREEZE";
                }

                $gameId = $betTicket['gameId'];
                $tableId = $betTicket['tableId'];
                $gameName = '';

                switch ($gameId) {
                    case 1:
                        if ($tableId == 20101 || $tableId == 20102 || $tableId == 20103 || $tableId == 20105) {
                            $gameName = 'Baccarat';
                        }
                        if ($tableId == 30101 || $tableId == 30102 || $tableId == 30103 || $tableId == 30105) {
                            $gameName = 'Site Baccarat';
                        }
                        if ($tableId == 40101 || $tableId == 40102) {
                            $gameName = 'Poipet Baccarat';
                        }
                        if ($tableId == 40103) {
                            $gameName = 'Baccarat';
                        }
                        break;
                    case 2:
                        if ($tableId == 20201) {
                            $gameName = 'InBaccarat';
                        }
                        break;
                    case 3:
                        if ($tableId == 20301) {
                            $gameName = 'DragonTiger';
                        }
                        if ($tableId == 30301 || $tableId == 30302) {
                            $gameName = 'Site DragonTiger';
                        }
                        break;
                    case 4:
                        if ($tableId == 20401) {
                            $gameName = 'Roulette';
                        }
                        if ($tableId == 30401) {
                            $gameName = 'Site Roulette';
                        }
                        break;
                    case 5:
                        if ($tableId == 40501) {
                            $gameName = 'Poipet Sicbo';
                        }
                        break;
                    case 6:
                        if ($tableId == 30601) {
                            $gameName = 'FanTan Roulette';
                        }
                        break;
                    case 7:
                        if ($tableId == 40701) {
                            $gameName = 'Poipet Bull';
                        }
                        break;
                    case 10:
                        if ($tableId == 41001) {
                            $gameName = 'Poipet VIP Baccarat';
                        }
                        break;
                    case 14:
                        if ($tableId == 21401) {
                            $gameName = 'sedie';
                        }
                        break;
                    case 41:
                        if ($tableId == 84101 || $tableId == 84102 || $tableId == 84103 || $tableId == 84104 || $tableId == 84105 || $tableId == 84106) {
                            $gameName = 'blockchain baccarat';
                        }
                        break;
                    case 42:
                        if ($tableId == 84201 || $tableId == 84202) {
                            $gameName = 'blockchain DragonTiger';
                        }
                        break;
                    case 43:
                        if ($tableId == 84301) {
                            $gameName = 'blockchain Three Cards';
                        }
                        break;
                    case 44:
                        if ($tableId == 84401) {
                            $gameName = 'blockchain Bull bull';
                        }
                        break;
                    case 45:
                        if ($tableId == 84501) {
                            $gameName = 'blockchain ThreeFace';
                        }
                        break;
                }


                $betDetail = [
                    'bet_id' => "K855_".$betTicket['id'],
                    'product' => "K855",
                    'game' => $gameName,
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $betTicket['userName'],
                    'stake' => $betTicket['betPoints'], // 下注
                    'valid_stake' => $betTicket['betPoints'], // turn over
                    'payout' => $betTicket['winOrLoss'],
                    'winlose' => $betTicket['winOrLoss'] - $betTicket['betPoints'], 
                    'before_balance' => $betTicket['balanceBefore'],
                    'after_balance' => ($betTicket['balanceBefore'] + ($betTicket['winOrLoss'] - $betTicket['betPoints'])),
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['betTime'])->setTimezone('Asia/Singapore'),
                    'round_at' => Carbon::parse($betTicket['betTime'])->setTimezone('Asia/Singapore'),
                    'round_date' => Carbon::parse($betTicket['betTime'])->setTimezone('Asia/Singapore')->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];
                $upserts[] = $betDetail;
                $ticketIds[] = $betTicket['id'];

            }

            BetLog::upsertByChunk($upserts);
            if(count($ticketIds) != 0){
                _King855::updateBets($ticketIds);
            }
        }
    }
}