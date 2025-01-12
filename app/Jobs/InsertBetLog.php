<?php

namespace App\Jobs;

use App\Http\Helpers;
use App\Models\BetLog;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InsertBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $betLog;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($betLog)
    {
        $this->betLog = $betLog;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $betLog = BetLog::updateOrCreate([
            'bet_id' => $this->betLog['bet_id'],
        ], $this->betLog);

        try {
            if ($betLog && !$betLog->is_settle && $betLog->bet_status == "SETTLED") {
                $betLog->settle();
            }
        } catch (Exception $e) {
            Helpers::sendNotification('error 2: ' . $e->getMessage());
        }
    }
}
