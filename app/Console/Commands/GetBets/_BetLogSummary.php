<?php

namespace App\Console\Commands\GetBets;

use App\Jobs\ProcessKissHourlyBetLog;
use App\Models\BetLogSummary;
use App\Models\Member;
use App\Models\ProductReport;
use App\Models\ProductReportBilling;
use Carbon\Carbon;
use Illuminate\Console\Command;

use function Sentry\captureMessage;

class _BetLogSummary extends Command
{
    public $outputs = [];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_betLogSummary {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get bet log summary';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        captureMessage('Testingsumary');
        $date = $this->argument('date') ? Carbon::parse($this->argument('date'))->format('Y-m-d') : now()->format('Y-m-d');
        $summaryBetLogs = BetLogSummary::where('date', $date)->get();

        foreach ($summaryBetLogs as $log) {
            captureMessage(json_encode($log));
            ProductReport::updateOrCreate(
                [
                    'date' => $log->date,
                    'member_id' => $log->member_id,
                    'product_id' => $log->product_id,
                ],
                [
                    'profit_loss' => $log->profit_loss,
                ]
            );

            $member = Member::find($log->member_id);
            if (!$member) {
                captureMessage('Member not found' . $log->member_id);
                continue;
            }

            $uniqueKey = $log->date . "_" . $log->member_id . "_" . $log->product_id;

            if (!isset($this->outputs[$uniqueKey])) {
                $this->outputs[$uniqueKey] = [
                    'unique_key' => $uniqueKey,
                    'date' => $date,
                    'member_id' => $log->member_id,
                    'product_id' => $log->product_id,
                    'category' => $log->category,
                    'wager' => 0,
                    'openbets' => 0,
                    'turnover' => 0,
                    'realbets' => 0,
                    'profit_loss' => 0,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                ];
            }

            $this->outputs[$uniqueKey]['profit_loss'] = $log->profit_loss;
            captureMessage(json_encode($this->outputs));
        }

        ProductReportBilling::upsert($this->outputs, ['unique_key'], ['profit_loss']);

        return 0;
    }
}
