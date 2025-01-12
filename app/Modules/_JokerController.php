<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class _JokerController
{
    const API_URL = "https://w.api788.net/";
    const FORWARD_URL = "https://www.gwp6868.net/";
    const APP_ID = "THTP";
    const SECRET = "as6cqgptkmgtw";

    protected $AppId;
    protected $Secret;
    protected $Username;
    protected $Password;
    protected $RequestID;
    protected $Amount;
    protected $Date;
    protected $Time;
    protected $Page;
    protected $NextId;

    public static function init($function, $params)
    {
        $controller = new _JokerController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        $this->AppId = config('api.JOKER_APP_ID');
        $this->Secret = config('api.JOKER_SECRET');

        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function get_url($function)
    {
        return config('api.JOKER_LINK');
    }
    public function make_params($function)
    {
        switch ($function) {
            case "CU":
                // Create User
                return [
                    "Method" => "CU",
                    "Username" => $this->Username,
                    "Timestamp" => time(),
                ];
            case "SP":
                // Set Password
                return [
                    "Method" => "SP",
                    "Username" => $this->Username,
                    "Password" => $this->Password,
                    "Timestamp" => time(),
                ];
            case "GC":
                // Get Balance
                return [
                    "Method" => "GC",
                    "Username" => $this->Username,
                    "Timestamp" => time(),
                ];
            case "TC":
                // Deposit
                return [
                    "Method" => "TC",
                    "Username" => $this->Username,
                    "RequestID" => $this->RequestID,
                    "Amount" => $this->Amount,
                    "Timestamp" => time(),
                ];
            case "TCH":
                // Verify Deposit / Withdrawal 
                return [
                    "Method" => "TC",
                    "RequestID" => $this->RequestID,
                    "Timestamp" => time(),
                ];
            case "TS":
                // Fetch Gamelogs
                return [
                    "Method" => "TS",
                    "StartDate" => Carbon::parse($this->Date)->subHour()->format('Y-m-d H:00:00'),
                    "EndDate" => Carbon::parse($this->Date)->format('Y-m-d H:00:00'),
                    "NextId" => $this->NextId,
                    "Timestamp" => time(),
                    "Delay" => "0",
                ];
            case "TSM":
                // Fetch Gamelogs
                return [
                    "Method" => "TSM",
                    "StartDate" => Carbon::parse($this->Date)->subMinutes(10)->format('Y-m-d H:i:00'),
                    "EndDate" => Carbon::parse($this->Date)->format('Y-m-d H:i:00'),
                    "NextId" => $this->NextId,
                    "Timestamp" => time(),
                    "Delay" => "0",
                ];

            case "PLAY":
                return [
                    "Method" => "PLAY",
                    "Username" => $this->Username,
                    "Timestamp" => time(),
                ];

            case "ListGames":
                return [
                    "Method" => "ListGames",
                    "Timestamp" => time(),
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_JOKER,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
        

        $log = 'joker_api_records';
        if ($function == "TS" || $function == "TSM") {
            $log = 'joker_api_ticket_records';
        }
        if ($function == "TCD" || $function == "TCW" || $function == "TCH") {
            $log = 'joker_api_transfer_records';
        }
        if ($function == "GC") {
            $log = 'joker_api_balance_records';
        }
        $this->create_param($function, $params);

        $params = $this->make_params($function);
        Log::channel($log)->debug("$time Function : " . $function);
        Log::channel($log)->debug("$time Params : " . json_encode($params));
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
            ]);
            $response = $client->post($this->get_url($function), [
                'http_errors' => false,
                'headers'        => ['Content-Type' => 'application/json'],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'query' => [
                    'appid' => $this->AppId,
                    'signature' => urldecode($signature = SELF::generateSign($log, $this->AppId, $this->Secret, $params)),
                ],
                'body' => json_encode($params),
            ]);
            Log::channel($log)->debug("$time Signature : " . $signature);
            $status_code = $response->getStatusCode();
            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            Log::channel($log)->debug("$time Response : " . json_encode($response));
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            return [
                'status' => false,
                'status_code' => $status_code ?? 500,
                'exception' => true,
                'data' => [],
            ];
        }

        if (!$response) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time Status: Unknown");
            return [
                'status' => false,
                'status_code' => $status_code,
                'exception' => true,
                'data' => [],
            ];
        }
    
        return [
            'status' => true,
            'status_code' => $status_code,
            'exception' => false,
            'data' => $response,
        ];
    }

    public static function generateSign($log, $AppId, $Secret, $params)
    {
        ksort($params);
        Log::channel($log)->debug("Signature Before Hashed : " . urldecode(http_build_query($params, '', '&')));
        return urlencode(base64_encode(hash_hmac("sha1", urldecode(http_build_query($params, '', '&')), $Secret, TRUE)));
    }

    public static function generate_username()
    {
        return "601" . random_int(100000000, 9999999999);
    }

    public static function generate_password()
    {
        return "Abcd" . rand(1000, 9999);
    }

    public static function generateIP()
    {
        return "141.164.55.98";
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "zh";
        }
        return "en";
    }
}
