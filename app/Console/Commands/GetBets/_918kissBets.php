<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_918kiss;
use App\Jobs\ProcessKissGetMemberBetLog;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _918kissBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_918kissBets {date?}';

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
        $endTime = Carbon::parse($date)->subMinutes(10)->format('Y-m-d H:i:s');
        $startTime = Carbon::parse($endTime)->subHour()->format('Y-m-d H:i:s');
        
        $playerList = _918kiss::get_player_list($startTime, $endTime);

        $delay = 1;
        foreach ($playerList as $player) {
            ProcessKissGetMemberBetLog::dispatch($startTime, $endTime, $player['Account'])->delay($delay);
            $delay += 1;
        }

        return 0;
    }
}
