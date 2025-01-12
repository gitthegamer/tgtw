<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _MKController
{
    const ERRORS = [
        "S100" => "Success 成功",
        "F0001" => "签名不匹配",
        "F0002" => "SN不匹配",
        "F0003" => "参数无效",
        "F0004" => "货币不匹配",
        "F0005" => "玩家已存在",
        "F0006" => "玩家不存在",
        "F0007" => "会员不存在",
        "F0008" => "失败",
        "F0009" => "无效的方法",
        "F0010" => "无效的用户状态",
        "F0011" => "状态无需改变",
        "F0012" => "数据不在范围",
        "F0013" => "没有匹配到相应数据",
        "F0014" => "登录被禁止",
        "F0015" => "没有足够的分数",
        "F0016" => "不支持里码",
        "F0017" => "交易流水号不能重复",
        "F0018" => "系统繁忙",
        "F0019" => "时间格式错误",
        "F0020" => "时间范围超出限制(开始时间与结束时间之差不能大于120分钟)",
        "F0021" => "Operation cancelled操作取消",
        "M0001" => "System maintenance 系统维护",
        "M0002" => "System error 系统错误"
    ];

    protected $SN;
    protected $ID;
    protected $Method;
    protected $PlayerCode;
    protected $Signature;
    protected $LoginId;
    protected $Amount;
    protected $RefId;
    protected $PlayerName;
    protected $StartTime;
    protected $EndTime;
    protected $PageSize;
    protected $PageIndex;


    public static function init($function, $params)
    {
        $controller = new _MKController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->Signature = $this->encypt_to_token($function);
    }

    public function get_url($function)
    {
        return config('api.MK_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "UserInfo/CreatePlayer":
                return [
                    'ID' => $this->ID,
                    'Method' => 'CreatePlayer',
                    'SN' => $this->SN,
                    'PlayerCode' => $this->PlayerCode,
                    'PlayerName' => $this->PlayerName,
                    'Signature' => $this->Signature
                ];
            case "Account/GetBalance":
                return [
                    'SN' => $this->SN,
                    'ID' => $this->ID,
                    'Method' => 'GetBalance',
                    'LoginId' => $this->LoginId,
                    'Signature' => $this->Signature
                ];
            case "Account/SetBalanceTransfer":
                return [
                    'SN' => $this->SN,
                    'ID' => $this->ID,
                    'Method' => 'SetBalanceTransfer',
                    'LoginId' => $this->LoginId,
                    'Amount' => $this->Amount,
                    'Signature' => $this->Signature
                ];
            case "Account/GetTransferById":
                return [
                    'SN' => $this->SN,
                    'ID' => $this->ID,
                    'Method' => 'GetTransferById',
                    'LoginId' => $this->LoginId,
                    'RefId' => $this->RefId,
                    'Signature' => $this->Signature
                ];
            case "Game/GetGameRecord":
                return [
                    'SN' => $this->SN,
                    'ID' => $this->ID,
                    'Method' => 'GetTransferById',
                    'LoginId' => $this->LoginId,
                    'RefId' => $this->RefId,
                    'Signature' => $this->Signature
                ];
            case "UserInfo/GetLoginH5":
                return [
                    'SN' => $this->SN,
                    'ID' => $this->ID,
                    'Method' => 'GetLoginH5',
                    'LoginId' => $this->LoginId,
                    'Signature' => $this->Signature
                ];
            case "Game/GetGameRecordByTime":
                return [
                    'SN' => $this->SN,
                    'ID' => $this->ID,
                    'Method' => 'GetGameRecordByTime',
                    'StartTime' => $this->StartTime,
                    'EndTime' => $this->EndTime,
                    'PageSize' => $this->PageSize,
                    'PageIndex' => $this->PageIndex,
                    'Signature' => $this->Signature
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_MK,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $log = 'mk_api_records';
        if ($function == "Game/GetGameRecordByTime") {
            $log = 'mk_api_ticket_records';
        }
        if ($function == "Account/SetBalanceTransfer") {
            $log = 'mk_api_transfer_records';
        }
        if ($function == "Account/GetBalance") {
            $log = 'mk_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'body' => json_encode($this->make_params($function)),
            ]);

            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            ModelsLog::addLog($logForDB);
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

        if (!(isset($response['code']) && ($response['code'] == 'S100' || $response['code'] == 'F0005'))) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => (isset($response['code']) && ($response['code'] == 'S100' || $response['code'] == 'F0005')),
            'status_message' => isset($response['code']) ? (SELF::ERRORS[$response['code']] ?? "no message") : "no message",
            'data' => $response['data'] ?? null
        ];
    }

    public function encypt_to_token($function)
    {
        return md5($this->encypt_string($function));
    }


    public function encypt_string($function)
    {
        if ($function == 'UserInfo/CreatePlayer') {
            return $this->ID . "CreatePlayer" . $this->SN . $this->PlayerCode . config('api.MK_SECRET_KEY');
        }
        if ($function == 'Account/GetBalance') {
            return $this->ID . "GetBalance" . $this->SN . $this->LoginId . config('api.MK_SECRET_KEY');
        }
        if ($function == 'Account/SetBalanceTransfer') {
            return $this->ID . "SetBalanceTransfer" . $this->SN . $this->LoginId . config('api.MK_SECRET_KEY');
        }
        if ($function == 'Account/GetTransferById') {
            return $this->ID . "GetTransferById" . $this->SN . $this->LoginId. $this->RefId . config('api.MK_SECRET_KEY');
        }
        if ($function == 'Game/GetGameRecord') {
            return $this->ID . "GetGameRecord" . $this->SN . $this->LoginId . config('api.MK_SECRET_KEY');
        }
        if ($function == 'UserInfo/GetLoginH5') {
            return $this->ID . "GetLoginH5" . $this->SN . $this->LoginId . config('api.MK_SECRET_KEY');
        }
        if ($function == 'Game/GetGameRecordByTime') {
            return $this->ID . "GetGameRecordByTime" . $this->SN . $this->StartTime . $this->EndTime . config('api.MK_SECRET_KEY');
        }
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "En-us";
        }
        if (app()->getLocale() == "cn") {
            return "Zh-cn";
        }
        return "En-us";
    }
}