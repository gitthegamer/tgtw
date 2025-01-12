<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_918kiss;
use App\Jobs\ProcessKissHourlyBetLog;
use App\Models\BetLogSummary;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class _918kissSummaryBetLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_918kissSummaryBetLog {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get 918kiss summary bet log';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->copy();
        $startDate = $date->copy()->startOfDay()->format('Y-m-d H:i:s');
        $endDate = $date->copy()->endOfDay()->format('Y-m-d H:i:s');

        $data = _918kiss::get_player_list($startDate, $endDate);

        if (empty($data)) {
            return;
        }

        $product_code = '9K';

        foreach ($data as $item) {

            //this cache is to get member id and product id from member account table by player game id
            $memberAccount = Cache::remember(
                'kiss_member_account.' . $item['Account'],
                60 * 60 * 24,
                function () use ($item, $product_code) {
                    return MemberAccount::whereHas('product', function ($q) use ($product_code) {
                        $q->where('code', $product_code);
                    })->where('username', $item['Account'])->first();
                }
            );

            if (!$memberAccount) {
                continue;
            }

            $productId = $memberAccount->product_id;
            $memberId = $memberAccount->member_id;

            $product = Cache::remember(
                'kiss_summary.' . $productId,
                60 * 60 * 24,
                function () use ($productId) {
                    return Product::where('id', $productId)->first();
                }
            );

            // kiss no give stake, valid stake, payout, so only can store profit loss
            BetLogSummary::updateOrCreate(
                [
                    'date' => $date->format('Y-m-d'),
                    'member_id' => $memberId,
                    'product_id' => $productId,
                ],
                [
                    'category' => $product->category,
                    'stake' => $item['press'],
                    'valid_stake' => $item['press'],
                    'payout' => $item['press'] + ($item['win'] * -1),
                    'profit_loss' => $item['win'] * -1, // -1 used to change view from player side to company side
                ]
            );
        }

        return 0;
    }
}
