<?php

namespace App\Modules;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _Pussy888Controller
{
    const BASE_LINK_ONE = "http://api.pussy888.com/";
    const BASE_LINK_TWO = "http://api2.pussy888.com/";

    const ERRORS = [
        "0" => "Success",
        "-1" => "Account Exist",
        "-2" => "Signature Failed",
        "-99" => "Illegal Action",
    ];

    protected $agentName;
    protected $authcode;
    protected $secretkey;
    protected $action;
    protected $userName;
    protected $password;
    protected $orderid;
    protected $scoreNum;
    protected $pageIndex;
    protected $date;
    protected $ActionUser;
    protected $eDate;
    protected $sDate;

    public static function init($function, $params)
    {
        $controller = new _Pussy888Controller();
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
        if ($function == "ashx/GameLog.ashx" || $function == "ashx/UserscoreLog.ashx" || $function == "ashx/AgentTotalReport.ashx") {
            return config('api.PUSSY_LINK_TWO') . $function;
        }
        return config('api.PUSSY_LINK_ONE') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "ashx/account/account.ashx?action=RandomUserName":
                return [
                    'userName' => $this->userName,
                    'UserAreaId' => 1,
                    "time" => $time = _Pussy888Controller::generateTime(),
                    "authcode" => $this->authcode,
                    "sign" => _Pussy888Controller::generateSign($this->authcode, $this->agentName, $time, $this->secretkey),
                ];
            case "ashx/account/account.ashx?action=getUserInfo":
                return [
                    'userName' => $this->userName,
                    'UserAreaId' => 1,
                    "time" => $time = _Pussy888Controller::generateTime(),
                    "authcode" => $this->authcode,
                    "sign" => _Pussy888Controller::generateSign($this->authcode, $this->userName, $time, $this->secretkey),
                ];
            case "ashx/account/account.ashx?action=addUser":
                return [
                    "action" => "addUser",
                    "agent" => $this->agentName,
                    "PassWd" => $this->password,
                    "userName" => $this->userName,
                    "pwdtype" => 1,
                    "Name" => "0123",
                    "Tel" => "0123",
                    "Memo" => "API",
                    "UserAreaId" => 1,
                    "UserType" => 1,
                    "time" => $time = _Pussy888Controller::generateTime(),
                    "authcode" => $this->authcode,
                    "sign" => _Pussy888Controller::generateSign($this->authcode, $this->userName, $time, $this->secretkey),
                ];
            case "ashx/account/setScore.ashx":
                return [
                    "action" => "setServerScore",
                    "orderid" => $this->orderid,
                    "scoreNum" => $this->scoreNum,
                    "userName" => $this->userName,
                    "ActionUser" => $this->agentName,
                    "ActionIp" => _Pussy888Controller::generateIP(),
                    "time" => $time = _Pussy888Controller::generateTime(),
                    "authcode" => $this->authcode,
                    "sign" => _Pussy888Controller::generateSign($this->authcode, $this->userName, $time, $this->secretkey),
                ];
            case "ashx/UserscoreLog.ashx":
                return [
                    "userName" => $this->userName,
                    "sDate" => $this->sDate,
                    "eDate" => $this->eDate,
                    "authcode" => $this->authcode,
                    "time" => $time = _Pussy888Controller::generateTime(),
                    "sign" => _Pussy888Controller::generateSign($this->authcode, $this->userName, $time, $this->secretkey),
                ];
            case "ashx/GameLog.ashx":
                return [
                    "pageIndex" => $this->pageIndex,
                    "pageSize" => 1000,
                    "userName" => $this->userName,
                    "sDate" => $this->sDate,
                    "eDate" => $this->eDate,
                    "time" => $time = _Pussy888Controller::generateTime(),
                    "authcode" => $this->authcode,
                    "sign" => _Pussy888Controller::generateSign($this->authcode, $this->userName, $time, $this->secretkey),
                ];
            case "ashx/AgentTotalReport.ashx":
                return [
                    "userName" => $this->userName,
                    "sDate" => $this->sDate,
                    "eDate" => $this->eDate,
                    'Type' => "ServerTotalReport",
                    "time" => $time = _Pussy888Controller::generateTime(),
                    "authcode" => $this->authcode,
                    "sign" => _Pussy888Controller::generateSign($this->authcode, $this->userName, $time, $this->secretkey),
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_PS,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       

        $log = 'pussy888_api_records';
        if ($function == "ashx/GameLog.ashx" || $function == "ashx/AgentTotalReport.ashx") {
            $log = 'pussy888_api_ticket_records';
        }
        if ($function == "ashx/account/setScore.ashx" || $function == "ashx/getOrder.ashx") {
            $log = 'pussy888_api_transfer_records';
        }
        if ($function == "ashx/account/account.ashx?action=getUserInfo") {
            $log = 'pussy888_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'form_params' => $this->make_params($function),
                'timeout' => 35,
            ]);
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
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time Status: Unknown");
            return [
                'status' => false,
                'status_message' => "Connection Error",
                'data' => null,
            ];
        }

        if(!$response['success']){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => $response['success'] ? true : false,
            'status_message' => $response['msg'] ?? "NO MSG",
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
