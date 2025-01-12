<?php

namespace App\Jobs;

use App\Helpers\BG;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBGBetDetail implements ShouldQueue
{
    public $bet;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($bet_id)
    {
        $this->bet = BetLog::where('bet_id', 'BG_'.$bet_id)->first();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // if (!$this->bet) {
        //     ProcessBGBetDetail::dispatch($this->bet)->delay(now()->addMinutes(5));
        //     return false;
        // }

        // if (!empty($this->bet->bet_detail)) {
        //     return false;
        // }

        // if (!$this->process()) {
        //     ProcessBGBetDetail::dispatch($this->bet)->delay(now()->addMinutes(5));
        //     return false;
        // }

        // return true;
    }

    public function process()
    {
        $betIdWithoutPrefix = str_replace('BG_', '', $this->bet->bet_id);
        $betRoundTickets = BG::getRoundBets(Carbon::now()->format('Y-m-d H:i:s'), $betIdWithoutPrefix);

        if (!$betRoundTickets) {
            return false;
        }

        $bet_detail = [
            'userId' => $betRoundTickets['userId'],
            'orderId' => $betRoundTickets['orderId'],
            'gameId' => $betRoundTickets['gameId'],
            'gameType' => $betRoundTickets['gameType'],
            'gameName' => $betRoundTickets['gameName'],
            'state' => $betRoundTickets['state'],
            'bettingType' => $betRoundTickets['bettingType'],
            'time' => $betRoundTickets['time'],
            'amount' => $betRoundTickets['amount'],
            'payment' => $betRoundTickets['payment'],
            'serialNo' => $betRoundTickets['serialNo'],
            'tableId' => $betRoundTickets['tableId'],
            'result' => $betRoundTickets['result'],
            'baccarat' => $betRoundTickets['baccarat'],
            'baccarat64' => $betRoundTickets['baccarat64'],
            'openingTime' => $betRoundTickets['openingTime'],
            'paymentTime' => $betRoundTickets['paymentTime'],
            'validBet' => $betRoundTickets['validBet'],
            'bettingTypeName' => $betRoundTickets['bettingTypeName'],
            'resultMasterPics' => $betRoundTickets['resultMasterPics'],
            'resultCluserPics' => $betRoundTickets['resultCluserPics'],
            'fmtBetResult' => $betRoundTickets['fmtBetResult'],
            'loginId' => $betRoundTickets['loginId'],
        ];

        $this->bet->update(['bet_detail' => $bet_detail]);
        return true;
    }
}
