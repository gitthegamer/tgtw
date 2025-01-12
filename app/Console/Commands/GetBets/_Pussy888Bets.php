<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\Pussy888;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class _Pussy888Bets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_Pussy888Bets {date?}';

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
        $playerList = Pussy888::get_player_list($startDate, $endDate);
        foreach ($playerList as $player) {
            Pussy888::product_logs($startDate, $endDate, $player['Account'], 1);
        }

        return 0;
    }
}
