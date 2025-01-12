<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _PPController
{

    const ERRORS = [
        "0000" => "Success",
    ];

    protected $cert;
    protected $agentId;
    protected $userId;
    protected $currency;
    protected $betLimit;
    protected $language;
    protected $userName;
    protected $alluser;
    protected $userIds;
    protected $txCode;
    protected $amount;
    protected $transferAmount;
    protected $withdrawType;
    protected $isMobileLogin;
    protected $externalURL;
    protected $platform;
    protected $gameCode;
    protected $gameType;
    protected $startTime;
    protected $endTime;
    protected $gameForbidden;
    public static function init($function, $params)
    {
        $controller = new _PPController();
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
        return config('api.AWC_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "wallet/createMember":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'currency' => $this->currency,
                    'betLimit' => $this->betLimit,
                    'language' => $this->language,
                    'userName' => $this->userName
                ];
            case "wallet/getBalance";
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    "alluser" => $this->alluser,
                    'userIds' => $this->userIds,
                ];
            case "wallet/deposit":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'txCode' => $this->txCode,
                    'transferAmount' => $this->transferAmount
                ];
            case "wallet/withdraw":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'txCode' => $this->txCode,
                    'withdrawType' => $this->withdrawType,
                    'transferAmount' => $this->transferAmount
                ];
            case "wallet/checkTransferOperation":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'txCode' => $this->txCode,
                ];
            case "wallet/doLoginAndLaunchGame":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'isMobileLogin' => $this->isMobileLogin,
                    'externalURL' => $this->externalURL,
                    'platform' => $this->platform,
                    'gameCode' => $this->gameCode,
                    'language' => $this->language,
                    'betLimit' => $this->betLimit,
                    // 'gameType' => $this->gameType,
                ];
            case 'fetch/gzip/getTransactionByTxTime':
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    "startTime" => $this->startTime,
                    "endTime" => $this->endTime,
                    'platform' => $this->platform,
                    'currency' => $this->currency,
                ];
            case 'wallet/login':
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'platform' => $this->platform,
                    'gameType' => $this->gameType,
                    'language' => $this->language,
                    'isMobileLogin' => $this->isMobileLogin,
                    'gameForbidden' => $this->gameForbidden,
                    'externalURL' => $this->externalURL,
                ];

        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_PP,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       
        $this->create_param($function, $params);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {
                   
                },
                'query' => $this->make_params($function),
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

        if(($response['status'] !== "0000") && ($response['status'] !== "1001")){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }
        
        return [
            'status' => (($response['status'] == "0000") || ($response['status'] == "1001")),
            'status_message' => "",
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "cn";
        }
        return "en";
    }

}