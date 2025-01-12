<?php

namespace App\Console\Commands\GetBets;


use App\Helpers\_Sportsbook;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class _SportbookDailyBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_SportbookDailyBets {date?}';

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
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now();
        $interval = new DateInterval('P1D'); // Change interval to 1 day
        $endDate = $date->copy()->endOfDay();
        $startDate = $date->copy()->sub($interval)->startOfDay();

        $delay = 0; // Initialize delay
        while ($startDate <= $endDate) {
            $formattedDate = $startDate->format('Y-m-d H:i:s');
            Artisan::call('get_bets:_SportbookBets', ['date' => $formattedDate]);
            $startDate->addMinutes(30);
            $delay += 2;
        }


        return 0;
    }
}
