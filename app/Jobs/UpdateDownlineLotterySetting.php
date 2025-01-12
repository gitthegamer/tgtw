<?php

namespace App\Jobs;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDownlineLotterySetting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $memberId;
    protected $lotterySetting;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $memberId, int $lotterySetting)
    {
        $this->memberId = $memberId;
        $this->lotterySetting = $lotterySetting;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $member = Member::find($this->memberId);

        if (!$member) {
            return;
        }

        $member->updateLotterySettingForDownlines($this->lotterySetting);
    }
}
