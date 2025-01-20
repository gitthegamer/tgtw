<?php

namespace App\Http;

use Illuminate\Support\Facades\Cache;

class Helpers
{
    //take CHAT_ID from https://api.telegram.org/bot<YourBOTToken>/getUpdates

    public static function sendNotification($message)
    {
        $cacheKey = $message;
        if (Cache::has($cacheKey)) {
            return false;
        }

        $url = "https://api.telegram.org/bot" . config('api.TELEGRAM_BOT_TOKEN') . "/sendMessage?chat_id=" . config('api.TELEGRAM_GROUP_INTERNAL');
        $msg = config('api.APP_NAME') . ": " . $message;
        $url = $url . "&text=" . urlencode($msg) . "&reply_to_message_id=" . config('api.TELEGRAM_GROUP_INTERNAL_CHANNEL');

        return SELF::sendWithCache($url, $cacheKey, 60 * 3);
    }

    public static function sendNotification_dgpay($message)
    {
        $cacheKey = 'DGpay_telegram';

        if (Cache::has($cacheKey)) {
            return false;
        }

        $url = "https://api.telegram.org/bot" . config('api.TELEGRAM_BOT_TOKEN') . "/sendMessage?chat_id=" . config('api.TELEGRAM_GROUP_DGPAY');
        $msg = config('api.APP_NAME') . ": " . $message;
        $url = $url . "&text=" . urlencode($msg);

        return SELF::sendWithCache($url, $cacheKey, 60 * 5);
    }

    public static function sendNotification_sms($message)
    {
        $cacheKey = 'SMS_telegram';

        if (Cache::has($cacheKey)) {
            return false;
        }

        $url = "https://api.telegram.org/bot" . config('api.BOT_TOKEN') . "/sendMessage?chat_id=" . config('api.TELEGRAM_GROUP_SMS');
        $msg = config('api.APP_NAME') . ": " . $message;
        $url = $url . "&text=" . urlencode($msg);

        return SELF::sendWithCache($url, $cacheKey, 60 * 60 * 12);
    }

    private static function startSend($url)
    {
        if (env('APP_ENV') == "local") {
            return false;
        }

        $ch = curl_init();
        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        );
        curl_setopt_array($ch, $optArray);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    private static function sendWithCache($url, $cacheKey, $cacheDuration)
    {
        $response = SELF::startSend($url);

        if ($response !== false) {
            Cache::put($cacheKey, true, $cacheDuration);
        }

        return $response;
    }
}
