<?php

namespace App\Jobs;

use App\Models\AdvanceCredit;
use App\Models\Member;
use App\Models\MemberBonus;
use App\Models\MemberReward;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $transaction;
    public $status;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Transaction $transaction, $status = null)
    {
        $this->transaction = $transaction;
        $this->status = $status;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        cache()->lock('transaction_lock.' . $this->transaction->id, 10)->get(function () {
            $this->transaction = Transaction::find($this->transaction->id);
            if ($this->transaction->type == Transaction::TYPE_DEPOSIT) {
                if ($this->transaction->status == Transaction::STATUS_IN_PROGRESS) {
                    return $this->process_deposit_in_progress();
                }
                if ($this->transaction->status == Transaction::STATUS_PENDING) {
                    return $this->process_deposit_pending();
                }
            }
            if ($this->transaction->type == Transaction::TYPE_WITHDRAWAL) {
                if ($this->transaction->status == Transaction::STATUS_IN_PROGRESS) {
                    return $this->process_withdrawal_in_progress();
                }
                if ($this->transaction->status == Transaction::STATUS_PENDING) {
                    return $this->process_withdrawal_pending();
                }
                if ($this->transaction->status == Transaction::STATUS_APPROVED) {
                    return $this->process_withdrawal_approved();
                }
                if ($this->transaction->status == Transaction::STATUS_SUCCESS) {
                    return $this->process_withdrawal_success();
                }
            }
            if ($this->transaction->type == Transaction::TYPE_BONUS) {
                return $this->process_bonus();
            }
            if ($this->transaction->type == Transaction::TYPE_ADJUSTMENT) {
                if ($this->transaction->status == Transaction::STATUS_IN_PROGRESS) {
                    return $this->process_adjustment();
                }
            }
            if ($this->transaction->type == Transaction::TYPE_REBATE) {
                return $this->process_rebate();
            }
            if ($this->transaction->type == Transaction::TYPE_COMMISSION) {
                return $this->process_commission();
            }
            if ($this->transaction->type == Transaction::TYPE_LOAN_IN) {
                return $this->process_loan_in();
            }
            if ($this->transaction->type == Transaction::TYPE_LOAN_OUT) {
                return $this->process_loan_out();
            }
            if ($this->transaction->type == Transaction::TYPE_LOAN_CREDIT_OUT) {
                return $this->process_loan_credit_out();
            }
        });
    }

    public function process_deposit_in_progress()
    {
        $member = Member::find($this->transaction->member_id);
        if (($member->balance + $this->transaction->amount) < 0) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
            ]);
            return false;
        }
        $this->transaction->update([
            'status' => Transaction::STATUS_PENDING,
        ]);
        return true;
    }

    public function process_deposit_pending()
    {
        $member = Member::find($this->transaction->member_id);
        if (!$this->status) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
            ]);
            return true;
        }

        if ($member->getBalance() <= 1) {
            $activeBonuses = $member->member_active_bonuses()->get();
            if ($activeBonuses) {
                foreach ($activeBonuses as $bonus) {
                    $bonus->redeem('Success deposit to terminate active bonus');
                    $member->member_logs()->create([
                        'action_by' => 'SYSTEM',
                        'text' => 'Success deposit to terminate active bonus'
                    ]);
                }
            }
        }

        $freeCreditBonus = $member->member_free_credit_bonuses()->first();
        if ($freeCreditBonus) {
            $freeCreditBonus->clearFreeCreditBonus();
        }

        $member->increment('balance', $this->transaction->amount);
        if ($member->transactions()->whereIn('type', [
            Transaction::TYPE_DEPOSIT,
        ])->where('status', Transaction::STATUS_SUCCESS)->count()) {
            $isFirstDeposit = false;
        } else {
            $isFirstDeposit = true;
        }

        $this->transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
            'isFirstDeposit' => $isFirstDeposit,
        ]);

        Cache::forget('welcome_modal.' . $member->id);

        if ($this->transaction->promotion) {
            Transaction::create([
                'unique_id' => uniqid(),
                'member_id' => $member->id,
                'promotion_id' => $this->transaction->promotion_id,
                'type' => Transaction::TYPE_BONUS,
                'amount' => $this->transaction->amount,
                'status' => Transaction::STATUS_IN_PROGRESS,
            ]);
        } else {
            $no_bonus_turnover_multiplyer = Setting::get('no_bonus_turnover_multiplyer', 0);
            if ($no_bonus_turnover_multiplyer > 0 && $member->type == Member::TYPE_PLAYER) {
                $member->member_bonuses()->create([
                    'member_id' => $member->id,
                    'transaction_id' => $this->transaction->id,
                    'deposit' => $this->transaction->amount,
                    'bonus' => 0,
                    'balance' => $this->transaction->amount,
                    'remaining_balance' => $this->transaction->amount,
                    'turnover' => $this->transaction->amount * $no_bonus_turnover_multiplyer,
                    'remaining_turnover' => $this->transaction->amount * $no_bonus_turnover_multiplyer,
                    'winover' => 0,
                    'remaining_winover' => 0,
                    'status' => MemberBonus::STATUS_ACTIVE,
                    'bet_logs' => [],
                    'remark' => "",
                    'expired_at' => null,
                ]);
            }
        }

        // Member Reward
        foreach (MemberReward::deposit() as $type) {
            $member_reward = $member->member_rewards()->firstOrCreate([
                'type' => $type,
            ], [
                'amount' => 0,
            ]);

            $member_reward->increment('amount', $this->transaction->amount);
            $member_reward->calculate();
        }

        try {
            // Handle Payment Transaction
            if ($this->transaction->payment) {
                PaymentTransaction::create([
                    'payment_id' => $this->transaction->payment->id,
                    'date' => now()->format('Y-m-d'),
                    'type' => PaymentTransaction::TYPE_DEPOSIT,
                    'fee_percentage' => $this->transaction->payment->fee_percentage,
                    'amount' => $this->transaction->amount,
                    'remark' => "Transaction #" . $this->transaction->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::debug($e);
        }

        return true;
    }

    public function process_withdrawal_in_progress()
    {
        $member = Member::find($this->transaction->member_id);
        if ($member->product) {
            $member->withdrawal();
        }

        $member = Member::find($this->transaction->member_id);
        if (($member->balance - $this->transaction->amount) < 0) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
                'remark' => "Not enough balance",
            ]);
            return false;
        }

        $member->decrement('balance', $this->transaction->amount);
        $this->transaction->update([
            'status' => Transaction::STATUS_PENDING,
        ]);

        if ($member->member_free_credit_bonuses()->sum('balance') > 0) {
            $member = Member::find($this->transaction->member_id);
            $member->update(['balance' => 0]);
            if ($this->transaction->amount >= 50) {
                $this->transaction->update([
                    'amount' => 50,
                ]);
            } else {
                $this->transaction->update([
                    'amount' => $this->transaction->amount,
                ]);
            }
        }

        return true;
    }

    public function process_withdrawal_pending()
    {
        if (!$this->status) {
            $member = Member::find($this->transaction->member_id);
            $member->increment('balance', $this->transaction->amount);
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
            ]);
            return true;
        }

        $this->transaction->update([
            'status' => Transaction::STATUS_APPROVED,
        ]);
        return true;
    }

    public function process_withdrawal_approved()
    {
        if (!$this->status) {
            $member = Member::find($this->transaction->member_id);
            $member->increment('balance', $this->transaction->amount);
            $this->transaction->update([
                'status' => Transaction::STATUS_CANCEL,
            ]);
            return true;
        }

        $this->transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
        ]);
        return true;
    }

    public function process_withdrawal_success()
    {
        if (!$this->status) {
            $member = Member::find($this->transaction->member_id);
            $member->increment('balance', $this->transaction->amount);
            $this->transaction->update([
                'status' => Transaction::STATUS_REVOKE,
            ]);
            return true;
        }

        return true;
    }

    public function process_adjustment()
    {
        $member = Member::find($this->transaction->member_id);
        foreach ($member->member_accounts as $member_account) {
            if ($member_account->balance() > 1) {
                $member_account->withdrawalNow();
            }
        }

        $member = Member::find($this->transaction->member_id);
        if (($member->balance + $this->transaction->amount) < 0) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
            ]);
            return false;
        }
        $member->increment('balance', $this->transaction->amount);
        $this->transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
        ]);
        return true;
    }

    public function process_rebate()
    {
        if ($this->transaction->status != Transaction::STATUS_IN_PROGRESS) {
            return false;
        }
        $member = Member::find($this->transaction->member_id);
        if (($member->balance + $this->transaction->amount) < 0) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
            ]);
            return false;
        }
        $member->increment('balance', $this->transaction->amount);
        // $member->member_bonuses()->create([
        //     'member_id' => $member->id,
        //     'transaction_id' => $this->transaction->id,
        //     'deposit' => 0,
        //     'bonus' => $this->transaction->amount,
        //     'balance' => $this->transaction->amount,
        //     'remaining_balance' => $this->transaction->amount,
        //     'turnover' => $this->transaction->amount * Setting::get('rebate_turnover_multiplyer', 1),
        //     'remaining_turnover' => $this->transaction->amount * Setting::get('rebate_turnover_multiplyer', 1),
        //     'winover' => 0,
        //     'remaining_winover' => 0,
        //     'status' => MemberBonus::STATUS_ACTIVE,
        //     'bet_logs' => [],
        //     'remark' => "",
        //     'expired_at' => now()->addDays(30),
        // ]);
        $this->transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
        ]);
        return true;
    }

    public function process_commission()
    {
        if ($this->transaction->status != Transaction::STATUS_IN_PROGRESS) {
            return false;
        }
        $member = Member::find($this->transaction->member_id);
        if (($member->balance + $this->transaction->amount) < 0) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
            ]);
            return false;
        }
        $member->increment('balance', $this->transaction->amount);
        $member->member_bonuses()->create([
            'member_id' => $member->id,
            'transaction_id' => $this->transaction->id,
            'deposit' => 0,
            'bonus' => $this->transaction->amount,
            'balance' => $this->transaction->amount,
            'remaining_balance' => $this->transaction->amount,
            'turnover' => $this->transaction->amount,
            'remaining_turnover' => $this->transaction->amount,
            'winover' => 0,
            'remaining_winover' => 0,
            'status' => MemberBonus::STATUS_ACTIVE,
            'bet_logs' => [],
            'remark' => "",
            'expired_at' => now()->addDays(30),
        ]);
        $this->transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
        ]);
        return true;
    }

    public function process_bonus()
    {
        if ($this->transaction->status != Transaction::STATUS_IN_PROGRESS) {
            return false;
        }

        if (!$this->transaction->promotion) {
            return false;
        }

        $promotion = $this->transaction->promotion;

        if ($promotion->multiplier_type == 1 && $promotion->winover_multiplier == 0) {
            $this->transaction->update(['status' => Transaction::STATUS_FAIL, 'amount' => 0, 'remark' => "Incorrect Setting"]);
            return false;
        }

        if ($promotion->multiplier_type == 2 && $promotion->turnover_multiplier == 0) {
            $this->transaction->update(['status' => Transaction::STATUS_FAIL, 'amount' => 0, 'remark' => "Incorrect Setting"]);
            return false;
        }

        $deposit = $this->transaction->amount;

        if ($promotion->percentage > 0) {
            $bonus = $this->transaction->amount * ($promotion->percentage / 100);
        } else {

            $bonus = $promotion->reward;
        }

        if ($promotion->max_bonus && $bonus > $promotion->max_bonus) {
            $bonus = $promotion->max_bonus;
        }

        $turnover = ($promotion->multiplier_type == 2) ? ($deposit + $bonus) * $promotion->turnover_multiplier : 0;
        $winover = ($promotion->multiplier_type == 1) ? ($deposit + $bonus) * $promotion->winover_multiplier : 0;

        $member = Member::find($this->transaction->member_id);
        $member->increment('balance', $bonus);
        $member->member_bonuses()->create([
            'object_id' => $promotion ? $promotion->id : null,
            'object_type' => $promotion ? get_class($promotion) : null,
            'member_id' => $member->id,
            'transaction_id' => $this->transaction->id,
            'deposit' => $deposit,
            'bonus' => $bonus,
            'balance' => $deposit + $bonus,
            'remaining_balance' => $deposit + $bonus,
            'turnover' => $turnover,
            'remaining_turnover' => $turnover,
            'winover' => $winover,
            'remaining_winover' => $winover,
            'status' => MemberBonus::STATUS_ACTIVE,
            'bet_logs' => [],
            'remark' => "",
            'expired_at' => now()->addDays($promotion->expired_days),
        ]);

        $this->transaction->update([
            'amount' => $bonus,
            'status' => Transaction::STATUS_SUCCESS,
            'action_by' => "SYSTEM",
            'action_at' => now(),
        ]);

        return true;
    }

    public function process_loan_in()
    {
        if ($this->transaction->status != Transaction::STATUS_IN_PROGRESS) {
            return false;
        }

        $advanceCredit = AdvanceCredit::find($this->transaction->advance_credit_id);
        if (!$advanceCredit) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
                'remark' => 'Advance Credit not found'
            ]);
            return false;
        }

        $member = Member::find($this->transaction->member_id);
        if (!$member) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
                'remark' => 'Member not found'
            ]);
            return false;
        }

        if (($member->balance + $this->transaction->amount) < 0) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
                'remark' => 'Invalid balance'
            ]);
            return false;
        }
        $member->increment('balance', $this->transaction->amount);
        $this->transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
            'remark' => 'Loan In Success'
        ]);

        $advanceCredit->update([
            'status' => AdvanceCredit::STATUS_APPROVE,
            'action_by' => $this->transaction->action_by,
        ]);

        return true;
    }

    public function process_loan_out()
    {
        if ($this->transaction->status != Transaction::STATUS_IN_PROGRESS) {
            return false;
        }

        $advanceCredit = AdvanceCredit::find($this->transaction->advance_credit_id);
        if (!$advanceCredit) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
                'remark' => 'Advance Credit not found'
            ]);
            return false;
        }


        $advanceCredit->update([
            'leftover_balance' => $advanceCredit->leftover_balance - $this->transaction->amount,
        ]);

        if ($advanceCredit->leftover_balance <= 0) {
            $advanceCredit->update([
                'status' => AdvanceCredit::STATUS_SETTLE,
            ]);
        }

        $this->transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
        ]);
        return true;
    }

    public function process_loan_credit_out()
    {
        if ($this->transaction->status != Transaction::STATUS_IN_PROGRESS) {
            return false;
        }

        $advanceCredit = AdvanceCredit::find($this->transaction->advance_credit_id);
        if (!$advanceCredit) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
                'remark' => 'Advance Credit not found'
            ]);
            return false;
        }

        $member = Member::find($this->transaction->member_id);
        if (($member->balance - $this->transaction->amount) < 0) {
            $this->transaction->update([
                'status' => Transaction::STATUS_FAIL,
            ]);
            return false;
        }
        $member->decrement('balance', $this->transaction->amount);
        $advanceCredit->update([
            'leftover_balance' => $advanceCredit->leftover_balance - $this->transaction->amount,
        ]);

        if ($advanceCredit->leftover_balance <= 0) {
            $advanceCredit->update([
                'status' => AdvanceCredit::STATUS_SETTLE,
            ]);
        }

        $this->transaction->update([
            'status' => Transaction::STATUS_SUCCESS,
        ]);
        return true;
    }
}
