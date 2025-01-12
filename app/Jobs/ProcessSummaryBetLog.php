<?php

namespace App\Jobs;

use App\Models\BetLogSummary;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\ProductReport;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessSummaryBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $date;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($date)
    {
        $this->date = $date ? Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $date = $this->date;

        $summaryBetLogs = BetLogSummary::where('date', $date)->get();

        foreach ($summaryBetLogs as $log) {
            ProductReport::updateOrCreate(
                [
                    'date' => $log->date,
                    'member_id' => $log->member_id,
                    'product_id' => $log->product_id,
                ],
                [
                    // 'category' => $log->category,
                    // 'wager' => $log->wager,
                    // 'stake' => $log->stake,
                    // 'valid_stake' => $log->valid_stake,
                    // 'payout' => $log->payout,
                    'profit_loss' => $log->profit_loss,
                    // 'jackpot_win' => $log->jackpot_win,
                    // 'progressive_share' => $log->progressive_share,
                    // 'expenses' => $log->expenses,
                ]
            );
        }

    }
}
