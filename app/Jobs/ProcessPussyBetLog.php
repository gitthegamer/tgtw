<?php

namespace App\Jobs;

use App\Helpers\Pussy888;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPussyBetLog implements ShouldQueue
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
        $startDate = $date->copy()->subHours(1)->format('Y-m-d H:i:s');
        $endDate = $date->copy()->format('Y-m-d H:i:s');
        $playerList = Pussy888::get_player_list($startDate, $endDate);
        $delay = 1;
        foreach ($playerList as $player) {
            ProcessPussyGetMemberBetLog::dispatch($startDate, $endDate, $player['Account'])->delay($delay);
            $delay += 1;
        }
    }
}
