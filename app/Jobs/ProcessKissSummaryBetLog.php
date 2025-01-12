<?php

namespace App\Jobs;

use App\Helpers\_918kiss;
use App\Models\BetLogSummary;
use App\Models\MemberAccount;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessKissSummaryBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $date;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($date)
    {
        $this->date = $date ? Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $date = Carbon::parse($this->date);
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
                    'date' => $date,
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
    }
}
