<?php

namespace App\Modules;

use App\Models\ProviderLog;
use DateTime;
use DateTimeZone;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;
use App\Helpers\_CrowdPlay;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class _CrowdPlayController
{
    protected $host_id;
    protected $member_id;
    protected $currency;
    protected $amount;
    protected $transid;
    protected $trans_id;
    protected $access_token;
    protected $lang;
    protected $page_size;
    protected $key;
    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }
    public function make_params($function)
    {
        switch ($function) {
            case "api/user/create":
                return [
                    'host_id' => $this->host_id,
                    'member_id' => $this->member_id,
                    'currency' => $this->currency
                ];
            case "api/user/balance":
                return [
                    'host_id' => $this->host_id,
                    'member_id' => $this->member_id,
                ];
            case "api/user/deposit-v2":
                return [
                    'host_id' => $this->host_id,
                    'member_id' => $this->member_id,
                    'amount' => $this->amount,
                    'transid' => $this->transid
                ];
            case "api/user/withdraw-v2":
                return [
                    'host_id' => $this->host_id,
                    'member_id' => $this->member_id,
                    'amount' => $this->amount,
                    'transid' => $this->transid
                ];
            case "api/user/wallet-trans-status":
                return [
                    'host_id' => $this->host_id,
                    'trans_id' => $this->trans_id,
                ];
            case "api/user/generate-access-token":
                return [
                    'host_id' => $this->host_id,
                    'member_id' => $this->member_id,
                ];
            case "api/user/launch":
                return [
                    'host_id' => $this->host_id,
                    'access_token' => $this->access_token,
                    'lang' => $this->lang,
                ];
            case "api/report":
                return [
                    'host_id' => $this->host_id,
                    'key' => $this->key,
                    'page_size' => $this->page_size,
                ];
            default:
                return [];
        }
    }

    public function get_url($function)
    {
        return config("api.CROWDPLAY_LINK") . "/" . $function . "?";
    }

    public static function init($function, $params)
    {
        $controller = new _CrowdPlayController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "get";

        $logForDB = [
            'channel' => ModelsLog::CHANNEL_CROWDPLAY,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'CrowdPlay_api_records';
        if ($function === "api/report") {
            $log = 'CrowdPlay_api_ticket_records';
        } elseif (in_array($function, ["api/user/withdraw-v2", "api/user/deposit-v2", "api/user/wallet-trans-status"])) {
            $log = 'CrowdPlay_api_transfer_records';
        } elseif ($function === "api/user/balance") {
            $log = 'CrowdPlay_api_balance_records';
        }

        Log::channel($log)->debug("$time Function: $function");
        $this->create_param($function, $params);
        $params = $this->make_params($function);
        try {
            $headers = [];

            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->get_url($function), [
                'headers' => $headers,
                'query' => $params,
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
            ]);
            $response = @json_decode($response->getBody()->getContents(), true);
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
        $isSuccess = isset($response['data']) && $response['data']['status_code'] == "0";
        $logForDB['status'] = $isSuccess ? ModelsLog::STATUS_SUCCESS : ModelsLog::STATUS_ERROR;
        if (!$isSuccess) {
            ModelsLog::addLog($logForDB);
        }
        return [
            'status' => $isSuccess,
            'status_message' => "",
            'data' => $response
        ];
    }


    public static function getLocale()
    {
        if (request()->lang == "en") {
            return 'en-us';
        }
        if (request()->lang == "cn") {
            return 'zh-cn';
        }
        return 'en-US';
    }
}
