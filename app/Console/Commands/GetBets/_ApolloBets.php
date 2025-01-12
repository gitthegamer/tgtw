<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Apollo;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class _ApolloBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_ApolloBets {date?}';

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
        $date = $this->argument('date') ? Carbon::parse($this->argument('date'))->setTimezone('Asia/Kuala_Lumpur') : now();
        $starttime = $date->copy()->subMinutes(60)->startOfMinute()->toIso8601String();
        $endtime = $date->toIso8601String();
    
        $betTickets = _Apollo::getBets($starttime, $endtime);
        $this->process($betTickets);
    
        return 0;
    }
    

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                echo json_encode($betTicket) . "\n";
                $stake = abs($betTicket['bet']);
                $valid_stake = abs($betTicket['bet']);
                $winlose = $betTicket['win'] + $betTicket['bet'];
                $payout = $betTicket['win'];

                if ($winlose > 0) {
                    $payout_status = "WIN";
                } elseif ($winlose  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                if (!isset($betTicket['seqNo']) || !isset($betTicket['gType'])) {
                    $bet_status = "UNKNOWN";
                } else {
                    $bet_status = "SETTLED";
                }

                $betDetail = [
                    'bet_id' => "APOLLO_" . $betTicket['seqNo'],
                    'product' => "APOLLO",
                    'game' => $betTicket['gname'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => strtoupper($betTicket['uid']),
                    'stake' => $stake,
                    'valid_stake' => $valid_stake,
                    'payout' => $payout,
                    'winlose' => $winlose,
                    'jackpot_win' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['time'])->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'),
                    'round_at' => Carbon::parse($betTicket['time'])->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'),
                    'round_date' => Carbon::parse($betTicket['time'])->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                    'key' => null,
                ];
                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
