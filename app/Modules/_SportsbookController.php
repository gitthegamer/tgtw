<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _SportsbookController
{
    protected $CompanyKey;
    protected $ServerId;
    protected $Username;
    protected $Agent;
    protected $Amount;
    protected $txnId;
    protected $IsFullAmount;
    protected $Portfolio;
    protected $StartDate;
    protected $EndDate;
    protected $isGetDownline;
    protected $BetSettings;
    protected $min;
    protected $max;
    protected $MaxPerMatch;
    protected $CasinoTableLimit;


    public static function init($function, $params)
    {
        $controller = new _SportsbookController();
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
        return config('api.SBO_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "web-root/restricted/agent/register-agent.aspx":
                return [
                    "CompanyKey" => "4A2BFED67596482EA2B5E6A60F83441E",
                    "Username" => "Stargameagent",
                    "Password" => "Stargameagent123",
                    'ServerId' => $this->ServerId,
                    "Currency" => "MYR",
                    "min" => 5,
                    "max" => 500,
                    "MaxPerMatch" => 500,
                    "CasinoTableLimit" => 1,
                ];


            case "web-root/restricted/player/register-player.aspx":
                return [
                    'CompanyKey' => $this->CompanyKey,
                    'ServerId' => $this->ServerId,
                    'Username' => $this->Username,
                    'Agent' => $this->Agent
                ];
            case "web-root/restricted/player/get-player-balance.aspx":
                return [
                    'CompanyKey' => $this->CompanyKey,
                    'ServerId' => $this->ServerId,
                    'Username' => $this->Username,
                ];
            case "web-root/restricted/player/deposit.aspx":
                return [
                    'CompanyKey' => $this->CompanyKey,
                    'ServerId' => $this->ServerId,
                    'Username' => $this->Username,
                    'Amount' => (float) $this->Amount,
                    'txnId' => $this->txnId,
                ];
            case "web-root/restricted/player/withdraw.aspx":
                return [
                    'CompanyKey' => $this->CompanyKey,
                    'ServerId' => $this->ServerId,
                    'Username' => $this->Username,
                    'Amount' => (float) $this->Amount,
                    'txnId' => $this->txnId,
                    'IsFullAmount' => $this->IsFullAmount
                ];
            case "web-root/restricted/player/check-transaction-status.aspx":
                return [
                    'CompanyKey' => $this->CompanyKey,
                    'ServerId' => $this->ServerId,
                    'txnId' => $this->txnId,
                ];
            case "web-root/restricted/player/update-player-bet-settings.aspx":
                return [
                    'CompanyKey' => $this->CompanyKey,
                    'ServerId' => $this->ServerId,
                    'Username' => $this->Username,
                    'min' => $this->min,
                    'max' => $this->max,
                    'MaxPerMatch' => $this->MaxPerMatch,
                    'CasinoTableLimit' => $this->CasinoTableLimit
                ];
            case "web-root/restricted/player/login.aspx":
                return [
                    'CompanyKey' => $this->CompanyKey,
                    'ServerId' => $this->ServerId,
                    'Username' => $this->Username,
                    'Portfolio' => $this->Portfolio
                ];
            case "web-root/restricted/report/v2/get-bet-list-by-modify-date.aspx":
                return [
                    'companyKey' => $this->CompanyKey,
                    'serverId' => $this->ServerId,
                    'portfolio' => $this->Portfolio,
                    'startDate' => $this->StartDate,
                    'endDate' => $this->EndDate,
                    "isGetDownline " => $this->isGetDownline,
                ];
            case "web-root/restricted/agent/update-agent-preset-bet-setting-by-sportid-and-markettype.aspx":
                return [
                    "CompanyKey" => "4A2BFED67596482EA2B5E6A60F83441E",
                    "ServerId" => $this->ServerId,
                    "Username" => "Stargameagent",
                    'BetSettings' => [
                        "sport_type" => "ALL",
                        "market_type" => "ALL",
                        "min_bet" => 1,
                        "max_bet" => 1000,
                        "max_bet_per_match" => 1000
                    ],
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_SBO,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       

        $log = 'sportsbook_api_records';
        if ($function == "web-root/restricted/report/v2/get-bet-list-by-modify-date.aspx") {
            $log = 'sportsbook_api_ticket_records';
        }
        if ($function == "web-root/restricted/player/withdraw.aspx" || $function == "web-root/restricted/player/deposit.aspx" || $function == "web-root/restricted/player/check-transaction-status.aspx") {
            $log = 'sportsbook_api_transfer_records';
        }
        if ($function == "web-root/restricted/player/get-player-balance.aspx") {
            $log = 'sportsbook_api_balance_records';
        }
        Log::channel($log)->debug("$time Function: " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json'
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
            Log::channel($log)->debug("$time Exception: " . $e->getMessage());
            Log::channel($log)->debug("$time Stack trace: " . $e->getTraceAsString());
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

        if(isset($response['error']) && (!isset($response['error']['id']) || $response['error']['id'] !== 0)){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }


        return [
            'status' => isset($response['error']['id']) && $response['error']['id'] == 0,
            'status_message' => $response['error']['msg'] ?? "no message",
            'data' => $response
        ];
    }


    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }

        if (request()->lang == "cn") {
            return "zh-cn";
        }

        return "en";
    }
}
