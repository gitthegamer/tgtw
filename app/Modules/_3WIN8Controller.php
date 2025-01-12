<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _3WIN8Controller
{
    const ERROR_ARRAYS = [
        'AGENCY_INVALID' => 'Agent does not exist or invalid',
        'EMPTY_REPORT_SUMMARY' => 'The record is empty.',
        'GET_GAME_JP_FAIL' => 'Fail to get jackpot value.',
        'GET_GAME_LIST_FAIL' => 'No any record exists.',
        'INSUFFICIENT_AGENT_FUND' => 'Agent Insufficient fund',
        'INSUFFICIENT_USER_FUND' => 'User Insufficient fund',
        'LOGIN_FAIL' => 'Fail to login with this user',
        'MAX_10_MINS' => 'Datetime range don’t allow more than 10 mins.',
        'MAX_LAST_THREE_DAYS' => 'Max get last three days record.',
        'NETWORK_ISSUE' => 'Internet connection error',
        'PARAM_INVALID' => 'Invalid param(agid,userid,password,ip,lang)',
        'PLAY_GAME_FAIL' => 'User no allow to play this game',
        'RESET_PASSWORD_FAIL' => 'Fail to reset this user’s password',
        'SINGATURE_INVALID' => 'Signature invalid',
        'TRX_EXIST' => 'Duplicated transaction',
        'TRX_INVALID' => 'Transaction does not exist',
        'UPDATE_LANG_FAIL' => 'Fail to update user’s language',
        'UPDATE_ROLLING_FAIL' => 'Fail to update kiosk wallet rolling.',
        'USER_EXIST' => 'Duplicated username',
        'USER_INVALID' => 'User does not exist or invalid',
        'USER_STATUS_SUSPEND' => 'User already be suspended',
    ];

    protected $agid;
    protected $username;
    protected $password;
    protected $lang;
    protected $signature;
    protected $amount;
    protected $orderid;
    protected $game_code;
    protected $game_support;
    protected $game_back_url;
    protected $start_date;
    protected $end_date;

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->signature = $this->generate_signature($this->make_params($function), config('api.WIN38_KEY'));
    }

    public function make_params($function)
    {
        if ($function == "user_register") {
            return [
                'agid' => $this->agid,
                'username' => $this->username,
                'password' => $this->password,
                'lang' => $this->lang,
                'signature' => $this->signature,
            ];
        }

        if ($function == "user_detail") {
            return [
                'agid' => $this->agid,
                'username' => $this->username,
                'signature' => $this->signature,
            ];
        }

        if ($function == "user_transfer") {
            return [
                'agid' => $this->agid,
                'amount' => $this->amount,
                'username' =>  $this->username,
                'orderid' => $this->orderid,
                'signature' => $this->signature,
            ];
        }

        if ($function  == "user_transfer_detail") {
            return [
                'agid' => $this->agid,
                'username' =>  $this->username,
                'orderid' => $this->orderid,
                'signature' => $this->signature,
            ];
        }

        if ($function  == "user_game_list") {
            return [
                'agid' => $this->agid,
                'signature' => $this->signature,
            ];
        }

        if ($function  == "user_play_game") {
            return [
                'agid' => $this->agid,
                'username' =>  $this->username,
                'game_code' => $this->game_code,
                'game_support' => $this->game_support,
                'game_back_url' => $this->game_back_url,
                'signature' => $this->signature,
            ];
        }

        if ($function == "user_game_history") {
            return [
                'agid' => $this->agid,
                'start_date' =>  $this->start_date,
                'end_date' => $this->end_date,
                'signature' => $this->signature,
            ];
        }
    }

    public function get_url($function)
    {
        return config('api.WIN38_LINK') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _3WIN8Controller();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_3Win8,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       

        $log = '3win8_api_records';
        if ($function == "user_game_history") {
            $log = '3win8_api_ticket_records';
        }
        if ($function == "user_transfer" || $function == "user_transfer_detail") {
            $log = '3win8_api_transfer_records';
        }
        if ($function == "user_detail") {
            $log = '3win8_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);

        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        Log::channel($log)->debug("length: " . strlen(json_encode($this->make_params($function))));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($this->make_params($function))),
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'form_params' => $this->make_params($function),
            ]);

            $responseBody = $response->getBody()->getContents();
            Log::channel($log)->debug("$time Raw Response: " . $responseBody);
            $response = json_decode($responseBody, true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            Log::channel($log)->debug("$time Response: " . json_encode($response));
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            Log::channel($log)->error("Error: " . $e->getMessage());
            Log::channel($log)->error("Stack Trace: " . $e->getTraceAsString());

            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }

        if (!$response) {
            Log::channel($log)->debug("$time Status: Unknown");
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);

            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }

        if ($response['status'] === "OK") {
            return [
                'status' => true,
                'status_message' => $response['status'],
                'data' => $response
            ];
        } else {
            $error_message = isset(SELF::ERROR_ARRAYS[$response['error_code']]) ? SELF::ERROR_ARRAYS[$response['error_code']] : "Unknown Error";
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['message'] = $error_message;
            ModelsLog::addLog($logForDB);

            return [
                'status' => false,
                'status_message' => $error_message,
                'data' => $response['error_code']
            ];
        }
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "eng";
        }
        if (app()->getLocale() == "cn") {
            return "chs";
        }
        return "eng";
    }

    function generate_signature($params, $privateKey = false)
    {
        if (!empty($params['signature'])) {
            unset($params['signature']);
        }

        if (!empty($params)) {
            ksort($params);
        }

        $params['signature'] = sha1(implode("", $params) . $privateKey);
        return $params['signature'];
    }


    function verify_signature($Params, $privateKey = false)
    {
        if (!is_array($Params) || !$privateKey) {
            return false;
        }
        $CSignature = '';
        if (!empty($Params['signature'])) {
            $CSignature = $Params['signature'];
            unset($Params['signature']);
        }
        ksort($Params);
        $Signature = sha1(implode("", $Params) . $privateKey);
        return ($Signature === $CSignature) ? true : false;
    }
}
