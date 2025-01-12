<?php

namespace App\Jobs;

use App\Helpers\Pussy888;
use App\Models\BetLogProcessingBuffer;
use App\Models\BetLogProcessingPussy888;
use App\Models\MemberAccount;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;


class StoreToBetLogProcessingPussy implements ShouldQueue
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
        $this->date = $date;
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

        $data = Pussy888::get_player_list($startDate, $endDate);

        if (empty($data)) {
            return;
        }

        $product_code = 'PS';

        foreach ($data as $item) {
            $memberAccount = Cache::remember(
                'pussy_member_account.' . $item['Account'],
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

            $memberId = $memberAccount->member_id;

            BetLogProcessingBuffer::updateOrCreate(
                [
                    'date' => $date->format('Y-m-d'),
                    'member_id' => $memberId,
                    'type' =>  $item['type']
                ],
                [
                    'profit_loss' => $item['win'] * -1,
                ]
            );
        }
    }
}
