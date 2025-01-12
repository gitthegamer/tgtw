<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _RCB988Controller
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
    protected $timeFrom;
    protected $gameForbidden;
    protected $startTimeHr;
    protected $endTimeHr;

    public static function init($function, $params)
    {
        $controller = new _RCB988Controller();
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
        if ($function == "fetch/getSummaryByTxTimeHour" || 
        $function == "fetch/gzip/getTransactionByTxTime" || 
        $function == "fetch/gzip/getTransactionByUpdateDate" ||
        $function == "fetch/gzip/getTipTxnByTxTime" ||
        $function == "fetch/getTipSummary") {
            return config('api.SEXY_REPORT_LINK') . $function;
        }
        return config('api.SEXY_LINK') . $function;
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
                    'gameType' => $this->gameType,
                ];
            case 'wallet/login':
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'platform' => $this->platform,
                    'gameType' => $this->gameType,
                    'language' => $this->language,
                    'betLimit' => $this->betLimit,
                    'isMobileLogin' => $this->isMobileLogin,
                    'gameForbidden' => $this->gameForbidden,
                    'externalURL' => $this->externalURL,
                ];
            case "fetch/gzip/getTransactionByUpdateDate":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    "timeFrom" => $this->timeFrom,
                    'platform' => $this->platform,
                    'currency' => $this->currency,
                    'gameType' => $this->gameType
                ];
            case "fetch/gzip/getTransactionByTxTime":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                    'platform' => $this->platform,
                    'currency' => $this->currency,
                ];
            case "fetch/getSummaryByTxTimeHour":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                    'platform' => $this->platform,
                    'currency' => $this->currency,
                ];
            case "fetch/gzip/getTipTxnByTxTime":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                    'platform' => $this->platform,
                ];
            case "fetch/getTipSummary":
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'startTimeHr' => $this->startTimeHr,
                    'endTimeHr' => $this->endTimeHr,
                    'platform' => $this->platform,
                    'currency' => $this->currency,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_RCB988,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       

        $log = 'sexybcrt_api_records';
        if ($function == "fetch/gzip/getTransactionByUpdateDate") {
            $log = 'sexybcrt_api_ticket_records';
        }
        if ($function == "wallet/withdraw" || $function == "wallet/deposit" || $function == "wallet/checkTransferOperation") {
            $log = 'sexybcrt_api_transfer_records';
        }
        if ($function == "wallet/getBalance") {
            $log = 'sexybcrt_api_balance_records';
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
                'query' => $this->make_params($function),
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

        if(($response['status'] !== "0000") && ($response['status'] !== "1001")){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);

        }

        return [
            'status' => (($response['status'] == "0000") || ($response['status'] == "1001")) ? true : false,
            'status_message' => "",
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "en";
        }
        if (app()->getLocale() == "cn") {
            return "cn";
        }
        return "en";
    }
}
