<?php

namespace App\Jobs;

use App\Http\Helpers;
use App\Models\BetLogProcessingBuffer;
use App\Models\BetLogSummary;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessPussySummaryBetLog implements ShouldQueue
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

        $data = BetLogProcessingBuffer::where('date', $date->format('Y-m-d'))
            ->selectRaw('member_id, SUM(profit_loss) as profit_loss')
            ->groupBy('member_id')
            ->get();

        if ($data->isEmpty()) {
            return;
        }

        $product_code = 'PS';

        foreach ($data as $item) {

            $memberId = $item->member_id;

            $product = Cache::remember(
                'pussy_product.' . $product_code,
                60 * 60 * 24,
                function () use ($product_code) {
                    return Product::where('code', $product_code)->first();
                }
            );

            if (!$product) {
                continue;
            }

            BetLogSummary::updateOrCreate(
                [
                    'date' => $date,
                    'member_id' => $memberId,
                    'product_id' => $product->id,
                ],
                [
                    'category' => $product->category,
                    'profit_loss' => $item->profit_loss,
                ]
            );
        }
    }
}
