<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class _PGSController
{
    
    const ERRORS = [
        "0" => "Success",
        "3" => "Error",
        "4" => "Error",
    ];

    protected $playerName;
    protected $merchantSecretKey;
    protected $merchantID;
    protected $amount;
    protected $merchantOrderNo;
    protected $startTime;
    protected $PageIndex;

    protected $endTime;
    protected $pageSize;
    protected $pageIndex;
    protected $lang;
    protected $gameid;
    protected $BackUrl;
    protected $ExtraLink;
    protected $merchantTransactionNo;
    protected $gameID;


    public static function init($function, $params)
    {
        $controller = new _PGSController();
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
        return config('api.PGS_LINK_LIVE') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "player":
                return [
                    'playerName' => $this->playerName,
                    'merchantSecretKey' => $this->merchantSecretKey,
                    "merchantID" => $this->merchantID,
                ];
            case "wallet/amount":
                return [
                    "playerName" => $this->playerName,
                    'merchantSecretKey' => $this->merchantSecretKey,
                    "merchantID" => $this->merchantID,
                ];
            case "wallet/transfer-in":
                return [
                    'amount' => $this->amount,
                    'merchantOrderNo' => $this->merchantOrderNo,
                    'playerName' => $this->playerName,
                    'merchantSecretKey' => $this->merchantSecretKey,
                    'merchantID' => $this->merchantID,
                ];
            case "wallet/transfer-out":
                return [
                    'amount' => $this->amount,
                    'merchantOrderNo' => $this->merchantOrderNo,
                    "playerName" => $this->playerName,
                    'merchantSecretKey' => $this->merchantSecretKey,
                    "merchantID" => $this->merchantID,
                ];
            case "game-records":
                return [
                    'merchantSecretKey' => $this->merchantSecretKey,
                    "merchantID" => $this->merchantID,
                    "startTime" => $this->startTime,
                    "endTime" => $this->endTime,
                    "pageSize" => $this->pageSize,
                    "pageIndex" => $this->pageIndex,
                ];
            case "transfer":
                return [
                    'merchantSecretKey' => $this->merchantSecretKey,
                    "merchantID" => $this->merchantID,
                    'merchantTransactionNo' => $this->merchantTransactionNo,
                    
                ];
            case "game/link":
                return [
                    "playerName" => $this->playerName,
                    'merchantID' => $this->merchantID,
                    'merchantSecretKey' => $this->merchantSecretKey,
                    'lang' => $this->lang,
                    'gameID' => $this->gameID,
                ];
            case "games":
                return [
                    'merchantSecretKey' => $this->merchantSecretKey,
                    "merchantID" => $this->merchantID,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_PGS,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       

        $log = 'pgs_api_records';
        if ($function == "game-records") {
            $log = 'pgs_api_ticket_records';
        }
        if ($function == "wallet/transfer-out" || $function == "wallet/transfer-in" || $function == "transfer") {
            $log = 'pgs_api_transfer_records';
        }
        if ($function == "wallet/amount") {
            $log = 'pgs_api_balance_records';
        }

        $method = 'GET';
        if ($function == "wallet/transfer-out" || $function == "wallet/transfer-in" || $function == "player") {
            $method = 'POST';
        }
        $logForDB['method'] = $method;

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);


        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            
            $client = new Client(["base_uri" => $this->get_url($function), 'verify' => false]);
            $response = $client->request($method, "", $request = [
                'http_errors' => false,
                'headers'        => ['Content-Type' => 'application/json'],
                'on_stats'  => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                $method == 'POST' ? 'body' : 'query' => $method == 'POST' ? json_encode($this->make_params($function)) : $this->make_params($function),
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

        if($response['code'] !== 0){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }


        return [
            'status' => $response['code'] == 0 ? true : false,
            'status_message' => $response['message'] ?? "no message",
            'data' => $response['data']
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
        return "en-us";
    }
}
