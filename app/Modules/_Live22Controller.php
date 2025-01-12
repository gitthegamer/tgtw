<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _Live22Controller
{
    const ERRORS = [
        0 => "Success",
        3 => "Parameter Error",
        4 => "Signature Error",
    ];

    protected $OperatorId;
    protected $providercode;
    protected $RequestDateTime;
    protected $PlayerId;
    protected $password;
    protected $ReferenceId;
    protected $type;
    protected $Amount;
    protected $html5;
    protected $Signature;
    protected $reformatJson;
    protected $secretkey;
    protected $versionkey;
    protected $ticket;
    protected $Ip;
    protected $GameCode;
    protected $Currency;

    public static function init($function, $params)
    {
        $controller = new _Live22Controller();
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
        if ($function == 'fetchbykey.aspx') {
            return config('api.LIVE22_LINK') . $function;
        }
        return config('api.LIVE22_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "CreatePlayer":
                return [
                    'OperatorId' => $this->OperatorId,
                    'RequestDateTime' => $this->RequestDateTime,
                    'PlayerId' => $this->PlayerId,
                    'Signature' => $this->Signature,
                ];
            case "CheckBalance";
                return [
                    'OperatorId' => $this->OperatorId,
                    'RequestDateTime' => $this->RequestDateTime,
                    'PlayerId' => $this->PlayerId,
                    'Signature' => $this->Signature,
                ];
            case "Deposit":
                return [
                    'OperatorId' => $this->OperatorId,
                    'RequestDateTime' => $this->RequestDateTime,
                    'PlayerId' => $this->PlayerId,
                    'Signature' => $this->Signature,
                    'ReferenceId' => $this->ReferenceId,
                    'Amount' => $this->Amount,
                ];
            case "Withdraw":
                return [
                    'OperatorId' => $this->OperatorId,
                    'RequestDateTime' => $this->RequestDateTime,
                    'PlayerId' => $this->PlayerId,
                    'Signature' => $this->Signature,
                    'ReferenceId' => $this->ReferenceId,
                    'Amount' => $this->Amount,
                ];
            case "GameLogin":
                return [
                    'OperatorId' => $this->OperatorId,
                    'RequestDateTime' => $this->RequestDateTime,
                    'PlayerId' => $this->PlayerId,
                    'Ip' => $this->Ip,
                    'GameCode' => $this->GameCode,
                    'Currency' => $this->Currency,
                    'Signature' => $this->Signature,
                    'Lang' => SELF::getLocale()
                ];
            case "GetGameList":
                return [
                    'OperatorId' => $this->OperatorId,
                    'RequestDateTime' => $this->RequestDateTime,
                    'Signature' => $this->Signature,
                ];
            case "PullLog":
                return [
                    'OperatorId' => $this->OperatorId,
                    'RequestDateTime' => $this->RequestDateTime,
                    'Signature' => $this->Signature,
                ];
            case "FlagLog":
                return [
                    'OperatorId' => $this->OperatorId,
                    'RequestDateTime' => $this->RequestDateTime,
                    'Signature' => $this->Signature,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();

        $log = 'live22_api_records';
        if ($function == "fetchbykey.aspx" || $function == "markbyjson.aspx") {
            $log = 'live22_api_ticket_records';
        }
        if ($function == "Deposit" || $function == "Withdraw") {
            $log = 'live22_api_transfer_records';
        }
        if ($function == "CheckBalance") {
            $log = 'live22_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);

        $this->create_param($function, $params);
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_L22,
            'function' => $function,
            'params' => json_encode($this->make_params($function)),
            'method' => $method,
        ];

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'json' => $this->make_params($function),
            ]);

            $response = @json_decode($response->getBody(), true);
            Log::channel($log)->debug("$time Response: " . json_encode($response));
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
        } catch (Exception $e) {

            Log::channel($log)->error("Error: " . $e->getMessage());
            Log::channel($log)->error("Stack Trace: " . $e->getTraceAsString());
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
            Log::channel($log)->debug("$time Status: Unknown");
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);
            return [
                'status' => false,
                'status_message' => "Connection Error",
                'data' => null,
            ];
        }

        return [
            'status' => $response['Status'],
            'status_message' => $response['Description'] ?? "no message",
            'data' => $response
        ];
    }

    public function encypt_to_token($function)
    {
        return $this->encypt_string($function);
    }
    public function encypt_string($function)
    {
        if ($function == "CreatePlayer") {
            return md5($function . $this->RequestDateTime . $this->OperatorId . config('api.LIVE22_SECRET_KEY') . $this->PlayerId);
        }
        if ($function == "CheckBalance") {
            return md5($function . $this->RequestDateTime . $this->OperatorId . config('api.LIVE22_SECRET_KEY') . $this->PlayerId);
        }
        if ($function == "Deposit") {
            return md5($function . $this->RequestDateTime . $this->OperatorId . config('api.LIVE22_SECRET_KEY') . $this->PlayerId);
        }
        if ($function == "Withdraw") {
            return md5($function . $this->RequestDateTime . $this->OperatorId . config('api.LIVE22_SECRET_KEY') . $this->PlayerId);
        }
        if ($function == "GetGameList") {
            return md5($function . $this->RequestDateTime . $this->OperatorId . config('api.LIVE22_SECRET_KEY'));
        }
        if ($function == "GameLogin") {
            return md5($function . $this->RequestDateTime . $this->OperatorId . config('api.LIVE22_SECRET_KEY') . $this->PlayerId);
        }
        if ($function == "PullLog") {
            return md5($function . $this->RequestDateTime . $this->OperatorId . config('api.LIVE22_SECRET_KEY'));
        }
        if ($function == "FlagLog") {
            return md5($function . $this->RequestDateTime . $this->OperatorId . config('api.LIVE22_SECRET_KEY'));
        }
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en-us";
        }
        if (request()->lang == "cn") {
            return "zh-cn";
        }
        return "en-us";
    }
}
