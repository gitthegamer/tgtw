<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\Mega888;
use App\Models\Bet;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class _Mega888Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Mega888Bets {date?}';

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
        $startDate = $date->copy()->subHours(1)->format('Y-m-d H:i:s');
        $endDate = $date->copy()->format('Y-m-d H:i:s');
        $playerList = Mega888::getBetsMember($startDate, $endDate);
        
        foreach ($playerList as $player) {
            Mega888::getBets($startDate, $endDate, $player['loginId'], 1);
        };

        return 0;
    }
}
