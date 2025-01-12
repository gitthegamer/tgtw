<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\Notifications;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $notifications;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Notifications $notifications)
    {
        $this->notifications = Notifications::find($notifications->id);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $log = 'notification_records';
        $time = time();

        if (count($this->notifications->member_list) == 0) {
            $memberList = Member::whereNotNull('firebase_token')
                ->select('username', 'firebase_token')
                ->get()
                ->toArray();
        } else {
            $memberList = Member::whereNotNull('firebase_token')
                ->whereIn('id', $this->notifications->member_list)
                ->select('username', 'firebase_token')
                ->get()
                ->toArray();
        }


        // $registration_ids = array_column($memberList, 'firebase_token');
        $access_token = $this->getGoogleAccessToken();
        $total_send = count($memberList);
        $total_success = 0;
        $total_fail = 0;
        foreach ($memberList as $member) {
            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->post('https://fcm.googleapis.com/v1/projects/stargame-89ec1/messages:send', [
                    'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                        Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                        Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                    },
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $access_token,
                    ],
                    'body' => json_encode([
                        'message' => [
                            'notification' => [
                                'title' => $this->notifications->title,
                                'body' => $this->notifications->message,
                            ],
                            'token' => $member->firebase_token,
                            // 'token' => 'fHrywPJWRSS7V1d3ICxYxJ:APA91bFiQHSU2-cmN5MMwwrUYR19B1MuSviKliUUFHq22GR2CLQYzB4TrLQEIyNbzWznPDX2naBSaqLpDL4vE_xjr8KO6NavZbvYUcVEKyUHAvIVb8fuJL_Ex8HW4ZzBtrMlvN04P42f'
                        ],
                    ]),
                ]);

                $response = @json_decode($response->getBody(), true);
                $total_success++;
                Log::channel($log)->debug("$time Response: " . @json_encode($response));
            } catch (Exception $e) {
                $total_fail++;
                Log::channel($log)->debug("$time " . "failed to send");
            }
        }


        $this->notifications->update([
            'total_send' => $total_send,
            'total_success' => $total_success,
            'total_fail' => $total_fail,
        ]);
    }

    private function getGoogleAccessToken()
    {

        $credentialsFilePath = app_path("firebase/stargame-89ec1-baa0d327079c.json"); //replace this with your actual path and file name
        $client = new \Google_Client();
        $client->setAuthConfig($credentialsFilePath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->refreshTokenWithAssertion();
        $token = $client->getAccessToken();
        return $token['access_token'];
    }
}
