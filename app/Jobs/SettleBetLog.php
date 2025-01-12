<?php

namespace App\Jobs;

use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\MemberBonus;
use App\Models\MemberReward;
use App\Models\ReportMemberBonuses;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettleBetLog implements ShouldQueue, ShouldBeUnique
{
    public $bet_log;
    public $tries = 3;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BetLog $bet_log)
    {
        $this->bet_log = $bet_log;
    }

    public $timeout = 180; // Set the timeout in seconds

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->bet_log->id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $member_account = Cache::remember(
            'member_account.' . $this->bet_log->username . "." . $this->bet_log->product . "." . $this->bet_log->category,
            60 * 60 * 24,
            function () {
                return MemberAccount::whereHas('product', function ($q) {
                    $q->where('code', $this->bet_log->product)->where('category', $this->bet_log->category);
                })->where('username', $this->bet_log->username)->first();
            }
        );

        if ($member_account) {
            $member = $member_account->member;
            if ($member) {
                // Handle Bonus
                foreach ($member->member_active_bonuses as $member_active_bonus) {
                    if (!$member_active_bonus->can_product($this->bet_log->product_object)) {
                        continue;
                    }

                    if ($member_active_bonus->expired_at && now()->gte($member_active_bonus->expired_at)) {
                        $member_active_bonus->remove("expired");
                        continue;
                    }

                    if ($this->bet_log->round_at < $member_active_bonus->created_at) {
                        continue;
                    }

                    try {
                        ReportMemberBonuses::updateOrCreate([
                            'member_bonus_id' => $member_active_bonus->id,
                            'bet_id' => $this->bet_log->id,
                        ], [
                            'product' => $this->bet_log->product,
                            'stake' => $this->bet_log->stake,
                            'valid_stake' => $this->bet_log->valid_stake,
                            'payout' => $this->bet_log->payout,
                            'winlose' => $this->bet_log->winlose,
                            'date' => Carbon::parse($this->bet_log->round_at)->format('Y-m-d'),
                        ])->calculate();
                    } catch (\Exception $e) {
                        Helpers::sendNotification("ReportMemberBonuses ERROR. Bonus ID: " . $member_active_bonus->id . " Bet ID: " . $this->bet_log->id);
                        throw $e;
                    }

                    break;
                }

                // Handle Turnover
                foreach (MemberReward::turnover() as $type) {
                    $member_reward = $member->member_rewards()->firstOrCreate([
                        'type' => $type,
                    ], [
                        'amount' => 0,
                    ]);

                    $member_reward->increment('amount', $this->bet_log->valid_stake);
                    $member_reward->calculate();
                }

                // Handle Rewards
                foreach (MemberReward::reward() as $type) {
                    $member_reward = $member->member_rewards()->firstOrCreate([
                        'type' => $type,
                        'category' => $this->bet_log->category,
                    ], [
                        'amount' => 0,
                    ]);

                    $member_reward->increment('amount', $this->bet_log->valid_stake);
                    $member_reward->calculate();
                }
            }
        }
        $this->bet_log->update(['is_settle' => true]);
        usleep(10000);
    }

    public function timeout()
    {
        // Handle timeout logic if needed
    }
}
