<?php

namespace App\Jobs;

use App\Http\Helpers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Member;
use App\Models\MemberBonus;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransferJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $request;
    protected $memberId;
    protected $transfer_out;
    /**
     * Create a new job instance.
     *
     * @param array $request
     * @param int $memberId
     * @return void
     * 
     */
    public function __construct(array $request, $memberId, $transfer_out)
    {
        $this->request = $request;
        $this->memberId = $memberId;
        $this->transfer_out = $transfer_out;
    }

    /**
     * Execute the job.
     *
     * @return void
     * 
     */
    public function handle()
    {
        $member = Member::findOrFail($this->memberId);

        $phone_number = $this->request['phone'];
        $phone_number = preg_replace("/^(\+?601|601|01)/", "1", $phone_number);

        $target_member = Member::where('phone', $phone_number)
            ->where('status', Member::STATUS_ENABLED)
            ->first();



        if ($member->product) {
            $member->withdrawal();
        }

        $member = Member::find($member->id);

        if (($member->balance - $this->request['amount']) < 0) {
            $this->transfer_out->update(['status' => Transaction::STATUS_FAIL, 'remark' => 'Insufficient Balance']);
            throw new \Exception("Simulated failure.");
            return redirect()->back()->with('error', __("Insufficient Balance."));
        }

        $member->decrement('balance', $this->request['amount']);

        $transfer_in = Transaction::create([
            'unique_id' => uniqid(),
            'member_id' => $target_member->id,
            'type' => Transaction::TYPE_TRANSFER_IN,
            'amount' => $this->request['amount'],
            'status' => Transaction::STATUS_SUCCESS,
            'remark' => "Transfer From $member->username (" . json_encode($this->request['amount']) . ")",
        ]);

        if ($target_member->type == Member::TYPE_PLAYER) {
            $target_member->member_bonuses()->create([
                'member_id' => $target_member->id,
                'transaction_id' => $transfer_in->id,
                'deposit' => $transfer_in->amount,
                'bonus' => 0,
                'balance' => $transfer_in->amount,
                'remaining_balance' => $transfer_in->amount,
                'turnover' => $transfer_in->amount * Setting::get('transfer_credit_turnover_multiplyer', 1),
                'remaining_turnover' => $transfer_in->amount * Setting::get('transfer_credit_turnover_multiplyer', 1),
                'winover' => 0,
                'remaining_winover' => 0,
                'status' => MemberBonus::STATUS_ACTIVE,
                'bet_logs' => [],
                'remark' => "",
                'expired_at' => null,
            ]);
        }

        $target_member->increment('balance', $this->request['amount']);
        $this->transfer_out->update(['status' => Transaction::STATUS_SUCCESS]);

        return true;
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'transfer_' . $this->memberId . '_' . md5(json_encode($this->request));
    }

    public function failed(\Throwable $exception)
    {
        Helpers::sendNotification('Transfer credit failed');
    }

    /* @var int
     */
    public $uniqueFor = 3600;
}
