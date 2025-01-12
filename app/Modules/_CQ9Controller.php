<?php

namespace App\Modules;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _CQ9Controller
{

    const ERRORS = [
        "0" => "Success",
        "-1" => "Account Exist",
        "-2" => "Signature Failed",
        "-99" => "Illegal Action",
    ];

    protected $agentName;
    protected $authcode;
    protected $code;
    protected $secretkey;
    protected $action;
    protected $userName;
    protected $gamehall;
    protected $password;
    protected $orderid;
    protected $scoreNum;
    protected $pageIndex;
    protected $date;
    protected $ActionUser;
    protected $eDate;
    protected $sDate;
    protected $usertoken;
    protected $account;
    protected $mtcode;
    protected $amount;
    protected $starttime;
    protected $endtime;
    protected $page;


    public static function init($function, $params)
    {
        $controller = new _CQ9Controller();
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
        return config('api.CQ9_API_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "gameboy/player":
                return [
                    "account" => $this->account,
                    'password' =>  $this->password,
                ];
            case "gameboy/player/login":
                return [
                    "account" => $this->account,
                    'password' =>  $this->password,
                ];
            case "gameboy/player/lobbylink":
                return [
                    'usertoken' => $this->usertoken,
                ];
            case "gameboy/player/deposit":
                return [
                    "account" => $this->account,
                    "mtcode" => $this->mtcode,
                    "amount" => $this->amount,
                ];
            case "gameboy/player/withdraw":
                return [
                    "account" => $this->account,
                    "mtcode" => $this->mtcode,
                    "amount" => $this->amount,
                ];
            case "gameboy/order/view":
                return [
                    "starttime" => $this->starttime,
                    "endtime" => $this->endtime,
                    "page" => $this->page
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";

        if ($function == "gameboy/game/halls" || $function == "gameboy/game/list/:gamehall") {
            $method = "GET";
        } else {
            $method = "POST";
        }

        if (isset($params['account'])) {
            if ($function == ("gameboy/player/balance/" . $params['account'])) {
                $method = "GET";
            }
        }

        if (isset($params['mtcode'])) {
            if ($function == ("gameboy/transaction/record/" . $params['mtcode'])) {
                $method = "GET";
            }
        }

        if (isset($params['page'])) {
            if ($function == ("gameboy/order/view?" . "starttime=" . $params['starttime'] . "&" . "endtime=" . $params['endtime'] . "&" . "page=" . $params['page'])) {
                $method = "GET";
            }
        }

        $logForDB = [
            'channel' => ModelsLog::CHANNEL_CQ9,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'CQ9_api_records';

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request($method, $this->get_url($function), [
                'headers' => [
                    'Authorization' => config('api.CQ9_TOKEN'), // Replace with the appropriate media type
                    'Content-Type' => 'application/x-www-form-urlencoded' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'form_params' => $this->make_params($function),
            ]);
            $response = @json_decode($response->getBody(), true);
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

        if ($response['status']['code'] != 0 && $response['status']['code'] != 8) {
            $logForDB['message'] = $response['status']['message'] ?? "unknown error";
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => $response['status']['code'] == 0 ? true : false,
            'message' => $response['status']['message'] ?? "unknown error",
            'data' => $response
        ];
    }


    public static function generateSign($authcode, $userName, $time, $secretkey)
    {
        return strtoupper(md5(strtolower($authcode . $userName . $time . $secretkey)));
    }

    public static function generate_username()
    {
        return random_int(100000, 9999999);
    }

    public static function generateTime()
    {
        return (int) (microtime(true) * 1000);
    }

    public static function generateIP()
    {
        return "141.164.55.98";
    }
}
