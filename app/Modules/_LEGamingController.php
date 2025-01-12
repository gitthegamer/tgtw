<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;


class _LEGamingController
{
    protected $agent;
    protected $timestamp;
    protected $s;
    protected $money;
    protected $orderid;
    protected $ip;
    protected $lineCode;
    protected $startTime;
    protected $endTime;
    protected $account;
    protected $lang;

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }
    
    public function make_params($function)
    {
        switch ($function) {
            case "CreateUser":
                return [
                    'agent' => $this->agent,
                    'timestamp' => $this->timestamp,
                    's' => $this->s,
                    'account' => $this->account,
                    'money' => $this->money,
                    'orderid' => $this->orderid,
                    'ip' => $this->ip,
                    'lineCode' => $this->lineCode,
                ];
            case "GetBalance":
                return [
                    'agent' => $this->agent,
                    'timestamp' => $this->timestamp,
                    's' => $this->s,
                    'account' => $this->account,
                ];
            case "Deposit":
            case "Withdraw":
                return [
                    'agent' => $this->agent,
                    'timestamp' => $this->timestamp,
                    's' => $this->s,
                    'account' => $this->account,
                    'money' => $this->money,
                    'orderid' => $this->orderid,
                ];
            case "CheckTransaction":
                return [
                    'agent' => $this->agent,
                    'timestamp' => $this->timestamp,
                    's' => $this->s,
                    'orderid' => $this->orderid,
                ];
            case "Launch":
                return [
                    'agent' => $this->agent,
                    'timestamp' => $this->timestamp,
                    's' => $this->s,
                    'account' => $this->account,
                    'money' => $this->money,
                    'orderid' => $this->orderid,
                    'ip' => $this->ip,
                    'lineCode' => $this->lineCode,
                    'lang' => $this->lang,
                ];
            case "GetBets":
                return [
                    'agent' => $this->agent,
                    'timestamp' => $this->timestamp,
                    's' => $this->s,
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                ];
            default:
                return [];
        }
    }

    public function encrytData($function)
    {
        $key = config('api.LEGAMING_AES_KEY');
    
        switch ($function) {
            case "CreateUser":  
            case "Launch":
                $string = "s=" . $this->s . "&account=" . $this->account . "&money=" . $this->money . "&orderid=" . $this->orderid . "&ip=" . $this->ip . "&lineCode=" . $this->lineCode . "&lang=" . $this->lang;
                break;
            case "GetBalance":
                $string = "s=" . $this->s . "&account=" . $this->account;
                break;
            case "Deposit":
            case "Withdraw":
                $string = "s=" . $this->s . "&account=" . $this->account . "&money=" . $this->money . "&orderid=" . $this->orderid;
                break;
            case "CheckTransaction":
                $string = "s=" . $this->s . "&orderid=" . $this->orderid;
                break;
            case "GetBets":
                $string = "s=" . $this->s . "&startTime=" . $this->startTime . "&endTime=" . $this->endTime;
                break;
            default:
                return '';
        }

        $encrypted = openssl_encrypt(
            $string,
            'AES-128-ECB',
            $key,
            OPENSSL_RAW_DATA
        );

        $base64Encrypted = base64_encode($encrypted);

        return $base64Encrypted;
    }

    public function encryptKey($function)
    {
        $key = config('api.LEGAMING_MD5_KEY');
        return md5($this->agent .  $this->timestamp .  $key);
    }

    public function get_url($function)
    {
        $encryptedData = $this->encrytData($function);
        
        $encodedParam = urlencode($encryptedData);
    
        $baseURL = ($function === "GetBets")
            ? config('api.LEGAMING_RECORD_LINK')
            : config('api.LEGAMING_API_LINK');
    
        return $baseURL . "?agent=" . $this->agent . "&timestamp=" . $this->timestamp . "&param=" . $encodedParam . "&key=" . $this->encryptKey($function);
    }

    public static function init($function, $params)
    {
        $controller = new _LEGamingController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "get";

        $logForDB = [
            'channel' => ModelsLog::CHANNEL_LEGAMING,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'LeGaming_api_records';
        if ($function === "GetBets") {
            $log = 'LeGaming_api_ticket_records';
        } elseif ($function === "Deposit" || $function === "Withdraw" || $function === "CheckTransaction") {
            $log = 'LeGaming_api_transfer_records';
        } elseif ($function === "GetBalance") {
            $log = 'LeGaming_api_balance_records';
        }

        $this->create_param($function, $params);
        $params = $this->make_params($function);
        $url = $this->get_url($function);
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->$method($url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
            ]);
            $response = @json_decode($response->getBody()->getContents(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            Log::channel($log)->debug("$time Response: " . json_encode($response));
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }

        if (!$response) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time Status: Unknown");
            return [
                'status' => false,
                'status_message' => "Connection Error",
                'data' => null,
            ];
        }

        if ($function === "CheckTransaction") {
            $logForDB['status'] = (($response['d']['status'] == "0") && ($response['d']['code'] == "0")) ? ModelsLog::STATUS_SUCCESS : ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
            return [
                'status' => ($response['d']['code'] == "0" || $response['d']['code'] == "16") ? true : false,
                'status_message' => "",
                'data' => $response
            ];
        }

        if ($response['d']['code'] != "0" && $response['d']['code'] != "16") {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }
        
        return [
            'status' => ($response['d']['code'] == "0" || $response['d']['code'] == "16") ? true : false,
            'status_message' => "",
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return 'en_us';
        }
        if (request()->lang == "cn") {
            return 'zh_cn';
        }
        return 'en_us';
    }
}
