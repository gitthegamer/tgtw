<?php

namespace App\Jobs;

use App\Helpers\Pussy888;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPussyDailyBetLog implements ShouldQueue
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
        $date = $this->argument ? Carbon::parse($this->argument) : now()->copy();
        $startDate = $date->copy()->startOfDay()->addHour()->format('Y-m-d H:i:s');
        $endDate = $date->copy()->endOfDay()->format('Y-m-d H:i:s');
        $delay = 0;

        $currentHour = Carbon::parse($startDate);
        while ($currentHour->lessThanOrEqualTo(Carbon::parse($endDate))) {
            $nextHour = $currentHour->copy()->addHour();
            ProcessPussyHourlyBetLog::dispatch($currentHour)->delay($delay);
            $currentHour = $nextHour;
            $delay += 21;
        }
    }
}
