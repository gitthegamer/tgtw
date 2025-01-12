<?php

namespace App\Jobs;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMemberBalances implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $member;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Member $member)
    {
        $this->member = $member;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $cache = cache('member_balances', []);

        $cache[$this->member->id] = [
            'top_agent_id' => $this->member->top_agent_id,
            'upline_id' => $this->member->upline_id,
            'username' => $this->member->username,
            'balance' => $this->member->total_balance(),
            'last_updated' => now(),
        ];

        cache()->put('member_balances', $cache);
    }
}
