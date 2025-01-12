<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\Pussy888;
use App\Jobs\ProcessPussyHourlyBetLog;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class _Pussy888DailyBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Pussy888DailyBets {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->copy();
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

        return 0;
    }
}
