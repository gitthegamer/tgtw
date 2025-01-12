<?php

namespace App\Jobs;

use App\Helpers\_888king2;
use App\Helpers\_918kiss;
use App\Models\BetLog;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessKissGetMemberBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $member;
    protected $startDate;
    protected $endDate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($startDate, $endDate, $member)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->member = $member;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        _918kiss::product_logs($this->startDate, $this->endDate, $this->member, 1);
    }
}
