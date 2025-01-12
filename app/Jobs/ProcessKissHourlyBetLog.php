<?php

namespace App\Jobs;

use App\Helpers\_918kiss;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessKissHourlyBetLog implements ShouldQueue
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
        $startDate = $this->argument->copy()->subHour()->format('Y-m-d H:i:s');
        $endDate = $this->argument->copy()->endOfHour()->format('Y-m-d H:i:s');

        $playerList = _918kiss::get_player_list($startDate, $endDate);
        $delay = 1;
        foreach ($playerList as $player) {
            ProcessKissGetMemberBetLog::dispatch($startDate, $endDate, $player['Account'])->delay($delay);
            $delay += 1;
        }

    }
}
