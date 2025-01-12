<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\NotificationMember;
use App\Models\NotificationMessages;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;


class ProcessWebNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $member_list;
    public $title;
    public $summary;
    public $message;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($title, $summary, $message, $member_list)
    {
        $this->member_list = $member_list;
        $this->title = $title;
        $this->message = $message;
        $this->summary = $summary;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $memberList = $this->member_list;
        $time = time();

        if (empty($memberList)) {
            $memberList = Member::all()->toArray();
        }else {
            $memberList = Member::whereIn('id', $memberList)->get()->toArray();
        }

        $message = $this->message ?? '';
        $title = $this->title ?? config('api.APP_NAME');
        $summary = $this->summary ?? '';


        $uuid = Str::uuid();
        $notification = NotificationMessages::create([
            'uuid' => $uuid,
            'title' => $title,
            'summary' => $summary,
            'message' => $message,
            'date' => now()->format('Y-m-d H:i A'),
        ]);

        $memberChunkList = array_chunk($memberList ?? [], 500);
        foreach ($memberChunkList as $chunk) {
            foreach ($chunk as $member) {
                NotificationMember::create([
                    'notification_id' => $notification->id,
                    'code' => $member['code'],
                    'username' => $member['username'],
                    'status' => NotificationMember::STATUS_UNREAD
                ]);
            }
        }
    }
}
