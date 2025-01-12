<?php

namespace App\Modules;

use App\Models\Message;
use Illuminate\Support\Facades\Http;

class _SMSS360Controller
{
    const KEY = "JX7axLPayv";
    const EMAIL = "bytewave.code@gmail.com";
    const ERRORS = [
        'BF0015' => 'This message is not allowed to send without approved company name.',
        'DF0018' => 'Duplicate reference ID found.',
        'WF0016' => 'This message is not allowed to send without being whitelisted.',
        'LF0019' => 'Completed successfully (Low Balance).',
        'F1301' => 'Invalid API Key.',
        'F1302' => 'Your message content is blank.',
        'F1303' => 'Your account is not active. Please contact our support representative.',
        'F1304' => 'Your recipient(s) is invalid.',
        'F1305' => 'Your campaign sending completed successfully.',
        'F1306' => 'Kindly enter at least one recipient.',
        'F1307' => 'Maximum 10 SMS per recipient(s).',
        'F1308' => 'Maximum 100 recipient(s).',
        'F1309' => 'Your message contains international brand(s).',
        'F1310' => 'Insufficient balance.',
    ];

    public static function sendSMS($operator, $recipient, $message)
    {
        $message = Message::create([
            'operator_id' => $operator->id,
            'object' => _SMSS360Controller::class,
            'recipient' => $recipient,
            'message' => $message,
            'remark' => "Pending",
        ]);

        $response = Http::get('https://www.smss360.com/api/sendsms.php', [
            'email' => SELF::EMAIL,
            'key' => SELF::KEY,
            'recipient' => $recipient,
            'referenceID' => "grace_" . $message->id,
            'message' => $message->message,
        ]);

        $xml = simplexml_load_string($response->body());
        $json = json_encode($xml);
        $array = json_decode($json, TRUE);

        $message->update(['remark' => $array['statusMsg']]);
    }
}
