<?php

namespace App\Jobs;

use App\Helpers\_918kiss;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessKissBetLog implements ShouldQueue
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
        $startTime = Carbon::parse($date)->copy()->subHour()->format('Y-m-d H:i:s');
        $endTime = Carbon::parse($date)->copy()->format('Y-m-d H:i:s');
        $playerList = _918kiss::get_player_list($startTime, $endTime);

        $delay = 1;
        foreach ($playerList as $player) {
            ProcessKissGetMemberBetLog::dispatch($startTime, $endTime, $player['Account'])->delay($delay);
            $delay += 1;
        }

        Cache::put('918kiss_status', now());
    }
}
