<?php

namespace App\Modules;


use Exception;
use App\Models\Log as ModelsLog;

class _LionkingController
{
    const ERRORS = [
        "S100" => "Success",
        "F0001" => "Error",
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
    protected $Guid;
    protected $UserCode;
    protected $Password;
    protected $Language;
    protected $TimeStamp;
    protected $CallBackUrl;


    public static function init($function, $params)
    {
        $controller = new _LionkingController();
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
        if ($function == 'A9SchemeUrl') {
            return config('api.LK_LINK') . $function . config('api.LK_SIGNATURE_KEY');
        }
        return config('api.LK_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "UserInfo/CreatePlayer":
                return [
                    'ID' => $this->ID,
                    'Method' => 'CreatePlayer',
                    'SN' => config('api.LK_SIGNATURE_KEY'),
                    'PlayerCode' => $this->PlayerCode,
                    'PlayerName' => $this->PlayerName,
                    'Signature' => $this->Signature
                ];
            case "Account/GetBalance":
                return [
                    'SN' => config('api.LK_SIGNATURE_KEY'),
                    'ID' => $this->ID,
                    'Method' => 'GetBalance',
                    'LoginId' => $this->LoginId,
                    'Signature' => $this->Signature
                ];
            case "Account/SetBalanceTransfer":
                return [
                    'SN' => config('api.LK_SIGNATURE_KEY'),
                    'ID' => $this->ID,
                    'Method' => 'SetBalanceTransfer',
                    'LoginId' => $this->LoginId,
                    'Amount' => $this->Amount,
                    'Signature' => $this->Signature
                ];
            case "Account/GetTransferById":
                return [
                    'SN' => config('api.LK_SIGNATURE_KEY'),
                    'ID' => $this->ID,
                    'Method' => 'GetTransferById',
                    'LoginId' => $this->LoginId,
                    'RefId' => $this->RefId,
                    'Signature' => $this->Signature
                ];
            case "Game/GetGameRecordByTime":
                return [
                    'SN' => config('api.LK_SIGNATURE_KEY'),
                    'ID' => $this->ID,
                    'Method' => 'GetGameRecordByTime',
                    'StartTime' => $this->StartTime,
                    'EndTime' => $this->EndTime,
                    'PageSize' => $this->PageSize,
                    'PageIndex' => $this->PageIndex,
                    'Signature' => $this->Signature
                ];
            case "GetLoginTokenApp":
                return [
                    'SN' => config('api.LK_SIGNATURE_KEY'),
                    'Guid' => $this->Guid,
                    'Method' => 'GetLoginTokenApp',
                    'UserCode' => $this->UserCode,
                    'Password' => config('api.LK_SECRET_KEY'),
                    'CallBackUrl' => $this->CallBackUrl,
                    'Language' => strtolower($this->Language),
                    'TimeStamp' => strval($this->TimeStamp),
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_LK,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);
        if ($function == 'GetLoginTokenApp') {
            $jsonData = json_encode($this->make_params($function));
            $baseStr = base64_encode($jsonData);
            $scheme = "jqk://" . $baseStr;
            return $scheme;
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {
                  
                },
                'body' => json_encode($this->make_params($function)),
            ]);

            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
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

            return [
                'status' => false,
                'status_message' => "Connection Error",
                'data' => null,
            ];
        }

        if($response['code'] !== 'S100' && $response['code'] !== 'F0005'){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => $response['code'] == 'S100' || $response['code'] == 'F0005',
            'status_message' => SELF::ERRORS[$response['code']] ?? "no message",
            'data' => $response['data']
        ];
    }

    public function encypt_to_token($function)
    {
        return md5($this->encypt_string($function));
    }

    public function encypt_string($function)
    {
        if ($function == 'UserInfo/CreatePlayer') {
            return $this->ID . "CreatePlayer" . config('api.LK_SIGNATURE_KEY') . $this->PlayerCode . config('api.LK_SECRET_KEY');
        }
        if ($function == 'Account/GetBalance') {
            return $this->ID . "GetBalance" . config('api.LK_SIGNATURE_KEY') . $this->LoginId . config('api.LK_SECRET_KEY');
        }
        if ($function == 'Account/SetBalanceTransfer') {
            return $this->ID . "SetBalanceTransfer" . config('api.LK_SIGNATURE_KEY') . $this->LoginId . config('api.LK_SECRET_KEY');
        }
        if ($function == 'Account/GetTransferById') {
            return $this->ID . "GetTransferById" . config('api.LK_SIGNATURE_KEY') . $this->LoginId . $this->RefId . config('api.LK_SECRET_KEY');
        }
        if ($function == 'Game/GetGameRecordByTime') {
            return $this->ID . "GetGameRecordByTime" . config('api.LK_SIGNATURE_KEY') . $this->StartTime . $this->EndTime . config('api.LK_SECRET_KEY');
        }
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "En-us";
        }
        if (request()->lang == "cn") {
            return "Zh-cn";
        }
        return "En-us";
    }
}
