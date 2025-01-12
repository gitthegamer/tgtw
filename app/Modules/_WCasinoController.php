<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _WCasinoController
{

    const ERRORS = [
        0 => 'All Interface: Success',
        -1000 => 'All Interface: The system is error, please contact',
        -1001 => 'All Interface: Internal abnormalities, please contact',
        -1002 => 'All Interface: Parameter error, please check',
        -1005 => 'All Interface: Appid error',
        -1006 => 'All Interface: Appid does not exist, please contact',
        -1007 => 'All Interface: Sign error, please check',
        -1008 => 'All Interface: Expired',
        -1009 => 'All Interface: The request is too frequent',
        -1010 => 'All Interface: Illegal IP',
        -1011 => 'User Interface: Not registered, please register first',
        -1012 => 'User Interface: User does not exist',
        -1013 => 'User Interface: User already exists',
        -1014 => 'User Interface: User is prohibited',
        -1015 => 'User Interface: User password error',
        -1020 => 'Wallet Interface: User balance insufficient',
        -1021 => 'Wallet Interface: The amount of withdrawal of deposits is non-zero',
        -1022 => 'User Interface: Quota does not exist',
        -1030 => 'Wallet Interface: Insufficient deduce amount',
        -1040 => 'Verification Code: Verification code error',
        -1041 => 'All Interface: Illegal domain name',
        -3002 => 'All Interface: Your account has Insufficient funds. [3002]',
        -3003 => 'All Interface: Your stake had exceeded your bet limit settings, please try again. [3003]',
        -3004 => 'All Interface: Game disabled. Please contact your upline for details, thank you. [3004]',
        -3006 => 'All Interface: Win limit hit! Please contact your upline, thank you. [3006]',
        -3007 => 'All Interface: Lose limit hit! Please contact your upline, thank you. [3007]',
        -3008 => 'All Interface: You have no bet limit setting for this game, please contact your upline to set it. [3008]',
        -3009 => 'All Interface: Your bet limit had been updated, please try again. [3009]',
        -3014 => 'All Interface: The game is unavailable at the moment. Please contact CSD for details, thank you. [3014]',
        -3017 => 'All Interface: An error has occurred. Please contact CSD for details, thank you. [4003]',
        -3018 => 'All Interface: An error has occurred. Please contact CSD for details, thank you. [4004]',
        -3020 => 'All Interface: An error has occurred. Please contact CSD for details, thank you. [5003]',
    ];

    protected $appid;
    protected $username;
    protected $sign;
    protected $tradeno;
    protected $amount;
    protected $begintime;
    protected $endtime;
    protected $index;
    protected $size;
    protected $iscreate;
    protected $clienttype;
    protected $language;
    protected $qids;

    public static function init($function, $params)
    {
        $controller = new _WCasinoController();
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
        return config('api.WCASINO_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "user/balance";
                return [
                    'appid' => $this->appid,
                    'username' => $this->username,
                    'sign' => $this->sign,
                ];
            case "user/dw":
                return [
                    'appid' => $this->appid,
                    'username' => $this->username,
                    'tradeno' => $this->tradeno,
                    'amount' =>  $this->amount,
                    'sign' => $this->sign,
                ];
            case "user/trade":
                return [
                    'appid' => $this->appid,
                    'username' => $this->username,
                    'tradeno' => $this->tradeno,
                    'begintime' =>  $this->begintime,
                    'endtime' => $this->endtime,
                    'index' => $this->index,
                    'size' => $this->size,
                    'sign' => $this->sign,
                ];
            case "login":
                return [
                    'appid' => $this->appid,
                    'username' => $this->username,
                    'iscreate' => $this->iscreate,
                    'clienttype' =>  $this->clienttype,
                    'language' => $this->language,
                    'sign' => $this->sign,
                ];

            case "quota/set":
                return [
                    'appid' => $this->appid,
                    'username' => $this->username,
                    'qids' => $this->qids,
                    'sign' => $this->sign,
                ];
            case "quota/list":
                return [
                    'appid' => $this->appid,
                    'sign' => $this->sign,
                ];
            case "kickout":
                return [
                    'appid' => $this->appid,
                    'username' => $this->username,
                    'sign' => $this->sign,
                ];
            case "record/bets/detail":
                return [
                    'appid' => $this->appid,
                    'begintime' => $this->begintime,
                    'endtime' => $this->endtime,
                    'index' => $this->index,
                    'size' => $this->size,
                    'sign' => $this->sign,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        if ($function == "user/balance" || $function == "user/trade" || $function == "record/bets/detail") {
            $method = "GET";
        }
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_WCASINO,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'w_api_records';
        if ($function == "record/bets/detail") {
            $log = 'w_api_ticket_records';
        }
        if ($function == "user/dw" || $function == "user/trade") {
            $log = 'w_api_transfer_records';
        }
        if ($function == "user/balance") {
            $log = 'w_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            if ($function == "user/balance" || $function == "user/trade" || $function == "record/bets/detail") {
                $response = $client->get($this->get_url($function), [
                    'headers' => [
                        'Content-Type' => 'application/json', // Replace with the appropriate media type
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
                        'Content-Type' => 'application/json' // Replace with the appropriate media type
                    ],
                    'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                        Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                        Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                    },
                    'query' => $this->make_params($function),
                ]);
            }

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

        if (isset($response['desc'])) {
            $message = $response['desc'];
        } elseif (isset(SELF::ERRORS[$function][$response['result']])) {
            $message = SELF::ERRORS[$function][$response['result']];
        } else {
            $message = "Unknown Error";
        }

        Log::channel($log)->debug("$time Status Message: $message");

        if ($response['result'] < 0) {
            $logForDB['message'] = $message;
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }
        
        return [
            'status' => ($response['result'] >= 0) ? true : false,
            'status_message' => $message,
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return 2;
        }
        if (request()->lang == "cn") {
            return 1;
        }
        if (request()->lang == "bm") {
            return 11;
        }
        return 2;
    }
}
