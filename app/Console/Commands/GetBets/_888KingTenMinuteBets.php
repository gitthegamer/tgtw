<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_888king2;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\GameLogKey;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

class _888KingTenMinuteBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_888KingTenMinuteBets {date?}';

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

        $versionKeyList = BetLog::where('round_at', '>=', $date->clone()->subHour())
            ->where('product', '8K')
            ->where('bet_status', '!=', 'SETTLED')
            ->pluck('key')
            ->unique()
            ->values();

        foreach ($versionKeyList as $versionKey) {
            if ($versionKey == null) continue;

            $result = _888king2::getBets($versionKey, true);
            if (is_array($result) && isset($result['data'], $result['key'])) {
                $betTickets = $result['data'];
                $key        = $result['key'];
                $this->process($betTickets, $key);
            } else {
                Helpers::sendNotification('kris test 888king 10 minutes bet');
            }
        }

        return 0;
    }

    public function process($betTickets, $key)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            foreach ($betTickets as $betTicket) {

                if ($betTicket['payout_amount'] - $betTicket['bet_stake'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['payout_amount'] - $betTicket['bet_stake']  < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                $bet_status = "SETTLED";
                if (strtolower($betTicket['status']) != 'done') {
                    $bet_status = $betTicket['status'];
                }

                $betDetail = [
                    'bet_id' => '8K_' . $betTicket['ticket_id'],
                    'product' => "8K",
                    'game' => $betTicket['game_group'],
                    'category' => Product::CATEGORY_SLOTS,
                    'username' => $betTicket['username'],
                    'stake' => $betTicket['bet_stake'],
                    'valid_stake' => $betTicket['bet_stake'],
                    'payout' => $betTicket['payout_amount'],
                    'winlose' => $betTicket['payout_amount'] - $betTicket['bet_stake'],
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => Carbon::parse($betTicket['report_date']),
                    'round_at' => Carbon::parse($betTicket['report_date']),
                    'round_date' => Carbon::parse($betTicket['report_date'])->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                    'key' => $key
                ];

                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
