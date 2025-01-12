<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _AstarCasinoController
{

    const ERRORS = [
        0 => 'Success: Success',
        -101 => 'Account is deactivated: Account Stop',
        202 => 'Channel does not match: Invalid Channel',
        400 => 'Parameters do not match: Bad Request',
        500 => 'Server error: Server error',
        602 => 'User already exists: User Exists',
        603 => 'User status is wrong: Invalid User Status',
        624 => 'Insufficient balance: Lack Of Balance',
        627 => 'Duplicate order number: Serial Repeat',
        630 => 'Data expired: Data Expired',
        631 => 'Username contains keywords: Username contains keywords',
        1001 => 'Record does not exist: Record not exists',
        1002 => 'User does not exist: User does not exist',
        100000 => 'User is not registered: User no register',
        100004 => 'The game is not activated: Game closed',
        100005 => 'Frequently call: FREQUENTLY_CALL',
        100006 => 'Too many logins in a short period of time: FREQUENTLY_LOGIN',
        100007 => 'Server is busy: SERVER_BUSY',
        100008 => 'Duplicate agent name: AGENT_NAME_REPEAT',
        100012 => 'This currency is not supported: CURRENCY_NOT_SUPPORT',
        100019 => 'The agent name does not exist: AGENT_NOT_EXIST',
        100020 => 'The agent does not match the channel: AGENT_COMPANY_MATCH',
        100021 => 'The amount is too large and exceeds the threshold: AMOUNT_GREATER',
        100022 => 'Users can select up to 3 quotas: LIMIT_GREATER',
    ];

    protected $userName;
    protected $currency;
    protected $amount;
    protected $serial;
    protected $language;
    protected $startTime;
    protected $endTime;
    protected $correctTime;
    protected $pageIndex;
    protected $version;
    protected $limit;

    public static function init($function, $params)
    {
        $controller = new _AstarCasinoController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function get_url($function)
    {
        return config('api.ASTAR_LINK') . 'channelApi/V3/API/' . config('api.ASTAR_CHANNEL') . '/' . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "createUser":
                return [
                    'userName' => $this->userName,
                    'currency' => $this->currency,
                ];
            case "getBalance";
                return [
                    'userName' => $this->userName,
                ];
            case "deposit":
                return [
                    'userName' => $this->userName,
                    'amount' => $this->amount,
                    'serial' => $this->serial,
                ];
            case "withdraw":
                return [
                    'userName' => $this->userName,
                    'amount' => $this->amount,
                    'serial' => $this->serial,
                ];
            case "loginWithChannel":
                return [
                    'userName' => $this->userName,
                    'language' => $this->language,
                ];
            case "setUserLimit":
                return [
                    'userName' => $this->userName,
                    'limit' => $this->limit,
                ];
            case "logout":
                return [
                    'userName' => $this->userName,
                ];
            case "getTransList":
                return [
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                ];
            case "getRecordByCondition":
                return [
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                    'correctTime' => $this->correctTime,
                    'pageIndex' => $this->pageIndex,
                    'version' => $this->version,
                ];
        }
    }
    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        if ($function == "getBalance" || $function == "getTransList" || $function == "getRecordByCondition" || $function == "loginWithChannel") {
            $method = "GET";
        }
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_ASTAR,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'astar_api_records';
        if ($function == "getRecordByCondition") {
            $log = 'astar_api_ticket_records';
        }
        if ($function == "deposit" || $function == "getTransList" || $function == "withdraw") {
            $log = 'astar_api_transfer_records';
        }
        if ($function == "getBalance") {
            $log = 'astar_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            if ($function == "getBalance" || $function == "getTransList" || $function == "getRecordByCondition" || $function == "loginWithChannel") {
                $response = $client->get($this->get_url($function), [
                    'headers' => [
                        'Content-Type' => 'application/json', // Replace with the appropriate media type
                        'Authorization' => 'Bearer ' . trim(config('api.ASTAR_AUTHORIZATION')),
                        'Accept' => 'application/json',
                    ],
                    'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                        Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                        Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                    },
                    'query' => $this->make_params($function),
                ]);
            } else {
                $response = $client->post($this->get_url($function), [
                    'headers' => [
                        'Content-Type' => 'application/json', // Replace with the appropriate media type
                        'Authorization' => 'Bearer ' . trim(config('api.ASTAR_AUTHORIZATION')),
                        'Accept' => 'application/json',
                    ],
                    'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                        Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                        Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                    },
                    'json' => $this->make_params($function),
                ]);
            }

            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            // ModelsLog::addLog($logForDB);
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
            $logForDB['message'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time Status: Unknown");
            return [
                'status' => false,
                'status_message' => "Connection Error",
                'data' => null,
            ];
        }

        if (isset($response['message'])) {
            $message = $response['message'];
        } elseif (isset(SELF::ERRORS[$function][$response['state']])) {
            $message = SELF::ERRORS[$function][$response['state']];
        } else {
            $message = "Unknown Error";
        }

        Log::channel($log)->debug("$time Status Message: $message");

        if ($response['state'] != 0) {
            $logForDB['message'] = $message;
            $logForDB['status'] = ($response['state'] == 0) ? ModelsLog::STATUS_SUCCESS : ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => ($response['state'] == 0) ? true : false,
            'status_message' => $message,
            'data' => $response,
        ];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en-us";
        }
        if (request()->lang == "cn") {
            return "zh-cn";
        }
        if (request()->lang == "bm") {
            return "hi-in";
        }
        return "en-us";
    }
}
