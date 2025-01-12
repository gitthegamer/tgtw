<?php

namespace App\Jobs;

use App\Http\Helpers;
use App\Models\BetLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use function Sentry\captureException;

class ProcessInsertBetLog implements ShouldQueue
{
    public $chunk;
    public $tries = 5;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $chunk)
    {
        $this->chunk = $chunk;
    }

    public $timeout = 60; // Set the timeout in seconds

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $attempts = 0;
        $maxAttempts = 3;
        $delay = 100; // milliseconds

        while ($attempts < $maxAttempts) {
            try {
                BetLog::upsert($this->chunk, ['bet_id']);
                return;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 1213) { // Deadlock found
                    captureException($e);
                    $attempts++;
                    usleep($delay * 1000); // Delay before retry
                    continue;
                }
                throw $e;
            }
        }

        Log::error('Failed to upsert BetLog after multiple attempts due to deadlock.');
    }

    public function timeout()
    {
        // Handle timeout logic if needed
        Log::debug('SettleBetLog timeout.');
    }
}
