<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _M8Controller
{

    const ERRORS = [
        "0" => "Success",
        "1" => "Error",
    ];

    protected $agent;
    protected $secret;
    protected $username;
    protected $serial;
    protected $amount;
    protected $group_id;
    protected $accType;
    protected $lang;
    protected $ref;
    protected $fetch_ids;



    public static function init($function, $params)
    {
        $controller = new _M8Controller();
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
        return config('api.M8_LINK');
    }

    public function make_params($function)
    {
        switch ($function) {
            case "player":
                return [
                    'action' => "create",
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                    'username' => $this->username
                ];
            case "balance":
                return [
                    'action' => "balance",
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                    'username' => $this->username
                ];
            case "deposit":
                return [
                    'action' => "deposit",
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                    'username' => $this->username,
                    'serial' => $this->serial,
                    'amount' => $this->amount
                ];
            case "withdraw":
                return [
                    'action' => "withdraw",
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                    'username' => $this->username,
                    'serial' => $this->serial,
                    'amount' => $this->amount
                ];
            case "records":
                return [
                    'action' => "group",
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                    'group_id' => $this->group_id,
                    'lang' => "EN-US",
                ];
            case "fetch":
                return [
                    'action' => 'fetch',
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                ];
            case "fetch_result":
                return [
                    'action' => 'fetch_result',
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                ];
            case "login":
                return [
                    'action' => "login",
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                    'username' => $this->username,
                    'accType' => $this->accType,
                    'lang' => $this->lang,
                    'ref' => $this->ref
                ];
            case "check_payment":
                return [
                    'action' => "check_payment",
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                    'username' => $this->username,
                    'serial' => $this->serial,
                ];
            case "mark_fetched":
                return [
                    'action' => "mark_fetched",
                    'agent' => $this->agent,
                    'secret' => $this->secret,
                    'fetch_ids' => $this->fetch_ids
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_M8,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
        

        $log = 'm8_api_records';
        if ($function == "fetch_result" || $function == "mark_fetched") {
            $log = 'm8_api_ticket_records';
        }
        if ($function == "withdraw" || $function == "deposit" || $function == "check_payment") {
            $log = 'm8_api_transfer_records';
        }
        if ($function == "balance") {
            $log = 'm8_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json', // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());

                },
                // 'body' => json_encode($this->make_params($function))
                'query' => $this->make_params($function),
                // 'form_params' => $this->make_params($function),
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

        if($response['errcode'] != 0 && $response['errcode'] != 1){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);

        }


        return [
            'status' => ($response['errcode'] == 0 || $response['errcode'] == 1),
            'status_message' => $response['errtext'] ?? "no message",
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "EN-US";
        }
        if (request()->lang == "cn") {
            return "ZH-CN";
        }
        return "EN-US";
    }
}
