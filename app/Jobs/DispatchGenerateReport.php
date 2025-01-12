<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\ProductReport;
use Illuminate\Support\Facades\Cache;




class DispatchGenerateReport implements ShouldQueue
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
        
        $startOfTheDay = $this->date->copy()->startOfDay();
        $endOfTheDay = $this->date->copy()->endOfDay();

        $outputs = [];
        $bet_logs = BetLog::select([
            'id',
            'product',
            'category',
            'username',
            'stake',
            'valid_stake',
            'payout',
            'winlose',
            'jackpot_win',
            'progressive_share',
            'bet_status',
        ])->whereBetween('round_at', [$startOfTheDay, $endOfTheDay])->get();

        $betlogChunks = array_chunk($bet_logs->toArray() ?? [], 500);
        foreach ($betlogChunks as $chunk) {
            foreach ($chunk as $betlog) {
                $member_account = Cache::remember(
                    'member_account.' . $betlog['username'] . "." . $betlog['product'] . "." . $betlog['category'],
                    60 * 60 * 24,
                    function () use ($betlog) {
                        return MemberAccount::whereHas('product', function ($q) use ($betlog) {
                            $q->where('code', $betlog['product'])->where('category', $betlog['category']);
                        })->where('username', $betlog['username'])->first();
                    }
                );

                if (!$member_account) {
                    error_log("#" . $betlog['id'] . " ERROR , MEMBER ACCOUNT NOT FOUND");
                    continue;
                }

                if (!isset($outputs[$member_account->member_id][$member_account->product_id])) {
                    $outputs[$member_account->member_id][$member_account->product_id] = [
                        'wager' => 0,
                        'openbets' => 0,
                        'turnover' => 0,
                        'realbets' => 0,
                        'profit_loss' => 0,
                        'jackpot_win' => 0,
                        'progressive_share' => 0,
                    ];
                }
                
                $outputs[$member_account->member_id][$member_account->product_id]['wager']++;
                if ($betlog['bet_status'] == "OPEN") {
                    $outputs[$member_account->member_id][$member_account->product_id]['openbets'] += $betlog['stake'];
                }
                if ($betlog['bet_status'] == "SETTLED") {
                    $outputs[$member_account->member_id][$member_account->product_id]['turnover'] += $betlog['stake'];
                    $outputs[$member_account->member_id][$member_account->product_id]['realbets'] += $betlog['valid_stake'];
                    $outputs[$member_account->member_id][$member_account->product_id]['profit_loss'] += $betlog['winlose'];
                    $outputs[$member_account->member_id][$member_account->product_id]['jackpot_win'] += $betlog['jackpot_win'];
                    $outputs[$member_account->member_id][$member_account->product_id]['progressive_share'] += $betlog['progressive_share'];
                }
            }
        }

        foreach ($outputs as $member_id => $report) {
            foreach ($report as $product_id => $data) {
                ProductReport::updateOrCreate([
                    'date' => $this->date->format('Y-m-d'),
                    'member_id' => $member_id,
                    'product_id' => $product_id,
                ], $data);
            }
        }
    }
}
