<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _GW99Controller
{
    const ERROR_ARRAYS = [];
    protected $PlayerAccount;
    protected $Password;
    protected $Agent;
    protected $Amount;
    protected $ExternalTransactionId;
    protected $StartDate;
    protected $EndDate;
    protected $PageNumber;


    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function make_params($function)
    {
        if ($function == "Player/Create") {
            return [
                'PlayerAccount' => $this->PlayerAccount,
                'Password' => $this->Password,
                'Agent' => $this->Agent,
            ];
        }

        if ($function == "Player/Info") {
            return [
                'PlayerAccount' => $this->PlayerAccount,
            ];
        }
        if ($function == "Player/Token") {
            return [
                'PlayerAccount' => $this->PlayerAccount,
            ];
        }

        if ($function == "GameList") {
            return [];
        }

        if ($function == "Player/Freeze") {
            return [
                "PlayerAccount" => $this->PlayerAccount,
            ];
        }

        if ($function == "Player/Unfreeze") {
            return [
                "PlayerAccount" => $this->PlayerAccount,
            ];
        }

        if ($function == "Player/Kick") {
            return [
                "PlayerAccount" => $this->PlayerAccount,
            ];
        }


        if ($function == "Transaction/Create") {
            return [
                'PlayerAccount' => $this->PlayerAccount,
                'Amount' => $this->Amount,
                'ExternalTransactionId' => $this->ExternalTransactionId,
            ];
        }

        if ($function == "Transaction/Check") {
            return [
                'PlayerAccount' => $this->PlayerAccount,
                'ExternalTransactionId' => $this->ExternalTransactionId,
            ];
        }

        if ($function == "Game/Log/Agent") {
            return [
                'StartDate' => $this->StartDate,
                'EndDate' => $this->EndDate,
                'PageNumber' => $this->PageNumber,
            ];
        }
    }

    public function get_url($function)
    {
        return config('api.GW99_LINK') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _GW99Controller();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_GW99,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'gw99_api_records';
        if ($function == "Game/Log/Agent") {
            $log = 'gw99_api_ticket_records';
        }
        if ($function == "Transaction/Create") {
            $log = 'gw99_api_transfer_records';
        }
        if ($function == "Player/Info") {
            $log = 'gw99_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);

        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'merchantName' => config('api.GW99_AGENT'),
                    'signature' => _GW99Controller::generate_signature($params),
                    'Content-Type' => 'application/json'
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
            Log::channel($log)->debug("$time Response: " . @json_encode($response));
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
            $logForDB['status'] = ModelsLog::STATUS_FAILED;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);

            Log::channel($log)->debug("$time Status: Unknown");
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }

        // if (!isset($response['result']['code'])) {
        //     $logForDB['status'] = ModelsLog::STATUS_ERROR;
        //     $logForDB['trace'] = "$time Missing result code";
        //     ModelsLog::addLog($logForDB);

        //     Log::channel($log)->debug("$time Missing result code");
        //     return [
        //         'status' => false,
        //         'status_message' => "Missing result code",
        //         'data' => null,
        //     ];
        // }

        // if (
        //     $response['result']['code'] !== "0" && $response['result']['code'] !== 0
        //     && $response['result']['code'] !== -50001 && $response['result']['code'] !== "-50001"
        //     && $response['result']['code'] !== -90201 && $response['result']['code'] !== "-90201"
        //     && $response['result']['code'] !== -90211 && $response['result']['code'] !== "-90211"
        //     && $response['result']['code'] !== -90212 && $response['result']['code'] !== "-90212"
        //     && $response['result']['code'] !== -90506 && $response['result']['code'] !== "-90506"
        // ) {
        //     $logForDB['status'] = ModelsLog::STATUS_PENDING;
        //     ModelsLog::addLog($logForDB);
        // }

        return [
            'status' => ($response['result']['code'] == "0" || $response['result']['code'] == -50001
                || $response['result']['code'] == -90201 || $response['result']['code'] == -90211
                || $response['result']['code'] == -90212 || $response['result']['code'] == -90506) ? true : false,
            'status_message' => $response['result']['message'],
            'data' => $response['data']
        ];
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

    public static function generate_signature($params)
    {
        $new_params = [];
        foreach (explode("&", urldecode(http_build_query($params, '', '&'))) as $param) {
            $param = explode("=", $param);
            $new_params[$param[0]] = $param[1];
        }
        ksort($new_params);

        $signature = !empty($new_params) ? urldecode(http_build_query($new_params)) . "&PrivateKey=" . config('api.GW99_API_KEY') : "PrivateKey=" . config('api.GW99_API_KEY');
        $signature = base64_encode(hash("sha256", $signature, True));
        return $signature;
    }
}
