<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\MemberTree;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMemberTree implements ShouldQueue
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
        $member_trees = [];
        $member_trees[] = [
            'upline_type' => get_class($this->member),
            'upline_id' => $this->member->id,
            'downline_type' => null,
            'downline_id' => null,
            'level' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // Looping upline untill root of the tree
        $uplines = array();
        if ($upline = $this->member->upline) {
            do {
                $uplines[] = $upline;
            } while ($upline = $upline->upline);

            $uplines = array_reverse($uplines);

            $i = 1;
            foreach ($uplines as $upline) {
                $member_trees[] = [
                    'upline_type' => get_class($upline),
                    'upline_id' => $upline->id,
                    'downline_type' => get_class($this->member),
                    'downline_id' => $this->member->id,
                    'level' => $i++,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            MemberTree::insert($member_trees);
        }
    }
}
