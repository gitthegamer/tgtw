<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class _Obet33Controller
{
    protected $hash;
    protected $agentId;
    protected $userId;
    protected $amount;
    protected $serialNo;
    protected $apiKey;
    protected $startdate;
    protected $enddate;
    protected $lang;
    protected $mobile;

    public static function init($function, $params)
    {
        $controller = new _Obet33Controller();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {

        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->hash = $this->encypt_to_token($function);
    }

    public function get_url($function)
    {
        return config('api.OBET_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "api/sb/register":
                return [
                    'hash' => $this->hash,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                ];
            case "api/sb/bal":
                return [
                    'hash' => $this->hash,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                ];
            case "api/sb/fund":
                return [
                    'hash' => $this->hash,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'amount' => $this->amount,
                    'serialNo' => $this->serialNo,
                ];
            case "api/sb/fund/check":
                return [
                    'hash' => $this->hash,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'serialNo' => $this->serialNo,
                ];
            case "api/sb/open":
                return [
                    'hash' => $this->hash,
                    'agentId' => $this->agentId,
                    'userId' => $this->userId,
                    'lang' => $this->lang,
                    'mobile' => $this->mobile,
                ];
            case "api/sb/betinfo":
                return [
                    'hash' => $this->hash,
                    'agentId' => $this->agentId,
                    'lang' => _Obet33Controller::getLocale(),
                    "startdate" => $this->startdate,
                    "enddate" => $this->enddate,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_OBET33,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
        

        $log = 'obet33_api_records';
        if ($function == "api/sb/betinfo") {
            $log = 'obet33_api_ticket_records';
        }
        if ($function == "api/sb/fund" || $function == "api/sb/fund/check") {
            $log = 'obet33_api_transfer_records';
        }
        if ($function == "api/sb/bal") {
            $log = 'obet33_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));

        $method = 'GET';
        if ($function == "api/sb/fund" || $function == "api/sb/register" || $function == "api/sb/fund/check") {
            $method = 'POST';
        }
        $logForDB['method'] = $method;

        try {
            $client = new Client(["base_uri" => $this->get_url($function), 'verify' => false]);
            $response = $client->request($method, "", $request = [
                'http_errors' => false,
                'headers' => ['Content-Type' => 'application/json'],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                $method == 'POST' ? 'body' : 'query' => $method == 'POST' ? json_encode($this->make_params($function)) : $this->make_params($function),
            ]);

            $status_code = $response->getStatusCode();
            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);

            Log::channel($log)->debug("$time Response: " . @json_encode($response));
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => [],
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

        if($response['Status'] !== 'success'){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }
        
        return [
            'status' => $response['Status'] == 'success' ? true : false,
            'status_message' => $response['Message'] ?? "no message",
            'data' => $response['Data']
        ];
    }

    public function encypt_to_token($function)
    {
        if ($function == 'api/sb/betinfo') {
            return md5('k=' . $this->apiKey . '&a=' . $this->agentId);
        }
        return md5('k=' . $this->apiKey . '&a=' . $this->agentId . '&u=' . $this->userId);
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
