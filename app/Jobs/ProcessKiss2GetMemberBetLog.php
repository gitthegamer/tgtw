<?php

namespace App\Jobs;

use App\Helpers\_918kiss2;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessKiss2GetMemberBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $member;
    protected $startTime;
    protected $endTime;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($startTime, $endTime, $member)
    {
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->member = $member;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        _918kiss2::product_logs($this->startTime, $this->endTime, $this->member);
    }
}
