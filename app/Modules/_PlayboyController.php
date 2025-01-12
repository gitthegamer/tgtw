<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _PlayboyController
{
    const ERROR_ARRAYS = [];
    protected $PlayerAccount;
    protected $Password;
    protected $Agent;
    protected $Amount;
    protected $ExternalTransactionId;
    protected $PageNumber;
    protected $StartDate;
    protected $EndDate;


    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function make_params($function)
    {
        if ($function == "player/create") {
            return [
                'PlayerAccount' => $this->PlayerAccount,
                'Password' => $this->Password,
                'Agent' => $this->Agent,
            ];
        }

        if ($function == "player/info") {
            return [
                'PlayerAccount' => $this->PlayerAccount,
            ];
        }

        if ($function == "transaction/create") {
            return [
                'PlayerAccount' => $this->PlayerAccount,
                'Amount' => $this->Amount,
                'ExternalTransactionId' => $this->ExternalTransactionId,
            ];
        }
        if ($function == "game/log/agent") {
            return [
                'PageNumber' => $this->PageNumber,
                'StartDate' => $this->StartDate,
                'EndDate' => $this->EndDate,
                'Agent' => $this->Agent,
            ];
        }
    }

    public function get_url($function)
    {
        return config('api.PLAYBOY_LINK') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _PlayboyController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_PLAYBOY,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
        

        $log = 'suncity_api_records';
        if ($function == "game/log/agent") {
            $log = 'suncity_api_ticket_records';
        }
        if ($function == "transaction/create" || $function == "transaction/check") {
            $log = 'suncity_api_transfer_records';
        }
        if ($function == "player/info") {
            $log = 'suncity_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);

        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Headers: " . json_encode([
            'merchantName' => config('api.PLAYBOY_MERCHANT'),
            'Sign' => _PlayboyController::generate_signature($params),
            'Content-Type' => 'application/json'
        ]));
        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'merchantName' => config('api.PLAYBOY_MERCHANT'),
                    'Sign' => _PlayboyController::generate_signature($params),
                    'Content-Type' => 'application/json'
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                // 'form_params' => $this->make_params($function),
                // 'query' => $this->make_params($function),
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
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time Status: Unknown");

            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }


        if(!SELF::status_check($response['Code'])){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }
   

        return [
            'status' => SELF::status_check($response['Code']),
            'status_message' => $response['Message'],
            'data' => $response ?? null
        ];
    }

    public function status_check($input)
    {
        switch ($input) {
            case 0: // success
                return true;
            case -90608: // payment still in progress
                return true;
            case -90201:
                return true;
            default:
                return false;
        }
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
        ksort($params);
        $new_params = [];
        foreach (explode("&", urldecode(http_build_query($params, '', '&'))) as $param) {
            $param = explode("=", $param);
            $new_params[$param[0]] = $param[1];
        }

        $signature = !empty($new_params) ? urldecode(http_build_query($new_params)) . "&PrivateKey=" . config('api.PLAYBOY_API_KEY') : "PrivateKey=" . config('api.PLAYBOY_API_KEY');
        $signature = base64_encode(hash("sha256", $signature, True));
        
        return $signature;
    }
}
