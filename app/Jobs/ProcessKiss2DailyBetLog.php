<?php

namespace App\Jobs;

use App\Helpers\_918kiss2;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessKiss2DailyBetLog implements ShouldQueue
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
        $this->queue = 'fetch_bet_logs';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $date = $this->argument ? Carbon::parse($this->argument) : now()->copy();
        $endTime = Carbon::parse($date)->endOfDay();
        $startTime = Carbon::parse($date)->startOfDay();
        $playerList = _918kiss2::get_player_list($startTime, $endTime);


        foreach ($playerList as $player) {
            ProcessKiss2GetMemberBetLog::dispatch($startTime, $endTime, $player['playerid']);
        }
    }
}
