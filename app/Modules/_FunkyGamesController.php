<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class _FunkyGamesController
{

    const ERRORS = [
        "0" => "Success", // Interface call is successful.
        "400" => "Invalid Input", // Invalid input.
        "401" => "Player Not Login", // Player not logged in.
        "402" => "Insufficient Balance", // Insufficient balance.
        "405" => "Api Suspended", // API suspended.
        "511" => "Transaction not found", // Transaction not found.
        "512" => "Transaction already existed", // Transaction already existed.
        "513" => "Previous transaction hasn’t completed", // Previous transaction hasn’t completed.
        "514" => "Wallet not found", // Wallet not found.
        "3002" => "Report Invalid Input", // Report invalid input.
        "3003" => "Report Page Not Found", // Report page not found.
        "3004" => "Report GameCode Not Found", // Report game code not found.
        "10005" => "GameCode is not allowed. GameCode is XXXX", // Game code is not allowed. Game code is XXXX.
        "9999" => "Internal Server Error",
    ];

    protected $currency;
    protected $gameCode;
    protected $language;
    protected $playerId;
    protected $playerIp;
    protected $redirectUrl;
    protected $sessionId;
    protected $userName;
    protected $amount;
    protected $isTestAccount;
    protected $txId;
    protected $page;
    protected $startTime;
    protected $endTime;

    public static function init($function, $params)
    {
        $controller = new _FunkyGamesController();
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
        if ($function == "Funky/Report/GetBetList") {
            return config('api.FUNKYGAMES_REPORT_LINK') . $function;
        }
        return config('api.FUNKYGAMES_LINK') . $function;
    }

    public function make_params($function)
    {
        if ($function == "Funky/Game/LaunchGame") {
            return [
                'currency' => $this->currency,
                'gameCode' => $this->gameCode,
                'language' => $this->language,
                'playerId' => $this->playerId,
                'playerIp' => $this->playerIp,
                'redirectUrl' => $this->redirectUrl,
                'sessionId' => $this->sessionId, 
                'userName' => $this->userName,
            ];
        }
        if ($function == "Funky/Wallet/GetBalanceByCurrency") {
            return [
                'currency' => $this->currency,
                'playerId' => $this->playerId
            ];
        }
       if ($function == "Funky/Wallet/Deposit") {
            return [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'isTestAccount' => $this->isTestAccount,
                'playerId' => $this->playerId,
                'txId' => $this->txId,
            ];
        }

        if ($function == "Funky/Wallet/Withdraw") {
            return [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'isTestAccount' => $this->isTestAccount,
                'playerId' => $this->playerId,
                'txId' => $this->txId,
            ];
        }

        if ($function == "Funky/Game/GetGameList") {
            return [
                'language' => $this->language,
            ];
        }

        if ($function == "Funky/Game/GetLobbyGameList"){
            return [
                'language' => $this->language,
            ];
        }

        if ($function == "Funky/Wallet/CheckTransaction"){
            return [
                'playerId' => $this->playerId,
                'txId' => $this->txId,
            ];
        }

        if ($function == "Funky/Report/GetBetList"){
            return [
                'page' => $this->page,
                'startTime' => $this->startTime,
                'endTime' => $this->endTime,
            ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_FUNKYGAMES,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       

        $log = 'funkygames_api_records';
        if ($function == "Funky/Report/GetBetList") {
            $log = 'funkygames_api_ticket_records';
        }
        if ($function == "Funky/Wallet/CheckTransaction") {
            $log = 'funkygames_api_transfer_records';
        }
        if ($function == "Funky/Wallet/GetBalanceByCurrency") {
            $log = 'funkygames_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json', // Replace with the appropriate media type
                    'User-Agent' => config('api.FUNKYGAMES_AGENT'),
                    'Authentication' => config('api.FUNKYGAMES_AUTH'),
                    'X-Request-ID' => 'FT33_'.Str::uuid(),
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'body' => json_encode($this->make_params($function))
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

        if($response['errorCode'] != 0){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }


        return [
            'status' => ($response['errorCode'] == 0) ? true : false,
            'status_message' => $response['errorMessage'] ?? self::ERRORS[$response['errorCode']] ?? "Unknown Error",
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "EN";
        }
        if (app()->getLocale() == "cn") {
            return "ZH_CN";
        }
        if (request()->lang == "bm") {
            return "MS_MY";
        }
        return "EN";
    }
}
