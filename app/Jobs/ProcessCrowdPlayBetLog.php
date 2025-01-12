<?php

namespace App\Jobs;

use App\Helpers\_CrowdPlay;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCrowdPlayBetLog implements ShouldQueue
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
        $result = _CrowdPlay::getBets();

        if (!is_array($result) || !isset($result['data']) || !isset($result['key'])) {
            return 0;
        }

        $betTickets = $result['data'];
        $key        = $result['key'];
        $this->process($betTickets, $key);
    }


    public function process($betTickets, $key)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                echo json_encode($betTicket) . "\n";
                $stake = $betTicket['bet_stake'];
                $valid_stake = $betTicket['bet_stake'];
                $payout = $betTicket['payout_amount'];
                if ($betTicket['gain_amount'] == 0 && $betTicket['loss_amount'] !== 0) {
                    $winlose = $betTicket['loss_amount'] * -1;
                } elseif ($betTicket['gain_amount'] !== 0 && $betTicket['loss_amount'] == 0) {
                    $winlose = $betTicket['gain_amount'];
                } elseif ($betTicket['gain_amount'] == 0 && $betTicket['loss_amount'] == 0) {
                    $winlose = 0;
                }

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if ($betTicket['status'] == "done") {
                    $bet_status = "SETTLED";
                } elseif (!isset($betTicket['ticket_id'])) {
                    $bet_status = "UNKNOWN";
                } else {
                    $bet_status = "PENDING";
                }


                $betDetail = [
                    'bet_id' => "CROWDPLAY_" . $betTicket['ticket_id'],
                    'product' => "CROWDPLAY",
                    'game' => $betTicket['game_code'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['username'],
                    'stake' => $stake,
                    'valid_stake' => $valid_stake,
                    'payout' => $payout,
                    'winlose' => $winlose,
                    'jackpot_win' => $betTicket['jackpot'],
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['report_date'])->format('Y-m-d H:i:s'),
                    'round_at' => Carbon::parse($betTicket['report_date'])->format('Y-m-d H:i:s'),
                    'round_date' => Carbon::parse($betTicket['report_date'])->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                    'key' => $key,
                ];
                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
