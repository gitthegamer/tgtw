<?php

namespace App\Jobs;

use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMegaBetLog implements ShouldQueue
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
        $playerList = Mega888::getBetsMember($startDate, $endDate);

        $delay = 1;
        foreach ($playerList as $player) {
            ProcessMegaGetMemberBetLog::dispatch($startDate, $endDate, $player['loginId'])->delay($delay);
            $delay += 1;
        }
    }
}
