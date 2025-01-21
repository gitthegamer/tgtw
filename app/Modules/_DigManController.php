<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _DigManController
{

    const ERRORS = [
        "active_player.aspx" => [
            00 => "Success",
            61.01 => "API Key not found", 
        ],
        "kickout_player.aspx" => [
            00 => "Success",
            61.01 => "API Key not found", 
        ],
        "get_balance.aspx" => [
            00 => "Success",
            61.01 => "API Key not found", 
            61.02 => "Login ID not found",
        ],
        "deposit.aspx" => [
            00 => "Success",
            61.01 => "API Key not found", 
            "61.01a" => "Error creating nw player account",
            61.02 => "Login ID not found",
        ],
        "withdraw.aspx" => [
            00 => "Success",
            61.01 => "API Key not found", 
            61.03 => "Withdraw amount exceed balance",
            61.02 => "Login ID not found",
        ],
        "check_transfer.aspx" => [
            00 => "Success",
            61.01 => "API Key not found", 
        ],
        "get_cockfight_processed_ticket_2.aspx" => [
            00 => "Success",
            61.00 => "Time range exceed 24 hours",
            "61.00a" => "Repeat access within 60 secs",
            61.01 => "API Key not found", 
            61.03 => "Withdraw amount exceed balance",
            61.02 => "Login ID not found",
        ],
        "get_session_id.aspx" => [
            00 => "Success",
            61.01 => "API Key not found", 
        ],
    ];

    protected $appkey;
    protected $api_key;
    protected $agent_code;
    protected $login_id;
    protected $name;
    protected $amount;
    protected $ref_no;
    protected $start_datetime;
    protected $end_datetime;
    protected $session_id;
    protected $lang;

    public static function init($function, $params)
    {
        $controller = new _DigManController();
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
        return config('api.DIGMAN_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "active_player.aspx";
                return [
                    'api_key' => $this->api_key,
                    'agent_code' => $this->agent_code,
                    'login_id' => $this->login_id,
                ];
            case "get_balance.aspx":
                return [
                    'api_key' => $this->api_key,
                    'agent_code' => $this->agent_code,
                    'login_id' => $this->login_id,
                ];
            case "deposit.aspx":
                return [
                    'api_key' => $this->api_key,
                    'agent_code' => $this->agent_code,
                    'login_id' => $this->login_id,
                    'name' => $this->name,
                    'amount' => $this->amount,
                    'ref_no' => $this->ref_no,
                ];
            case "withdraw.aspx":
                return [
                    'api_key' => $this->api_key,
                    'agent_code' => $this->agent_code,
                    'login_id' => $this->login_id,
                    'amount' => $this->amount,
                    'ref_no' => $this->ref_no,
                ];
            case "kickout_player.aspx":
                return [
                    'api_key' => $this->api_key,
                    'agent_code' => $this->agent_code,
                    'login_id' => $this->login_id,
                ];
            case "check_transfer.aspx":
                return [
                    'api_key' => $this->api_key,
                    'agent_code' => $this->agent_code,
                    'ref_no' => $this->ref_no,
                ];
            case "get_session_id.aspx":
                return [
                    'api_key' => $this->api_key,
                    'agent_code' => $this->agent_code,
                    'login_id' => $this->login_id,
                    'name' => $this->name,
                ];
            case "get_cockfight_processed_ticket_2.aspx":
                return [
                    'api_key' => $this->api_key,
                    'agent_code' => $this->agent_code,
                    'start_datetime' => $this->start_datetime,
                    'end_datetime' => $this->end_datetime,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_DIGMAN,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'digman_api_records';
        if ($function == "get_cockfight_processed_ticket_2.aspx") {
            $log = 'digman_api_ticket_records';
        }
        if ($function == "deposit.aspx" || $function == "withdraw.aspx" || $function == "check_transfer.aspx") {
            $log = 'digman_api_transfer_records';
        }
        if ($function == "get_balance.aspx") {
            $log = 'digman_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    // 'Content-Type' => 'application/xml' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'query' => $this->make_params($function),
            ]);

            $response = @json_decode(json_encode(simplexml_load_string($response->getBody())), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            ModelsLog::addLog($logForDB);
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

        if (isset($response['status_text'])) {
            $message = $response['status_text'];
        } elseif (isset(SELF::ERRORS[$function][$response['status_code']])) {
            $message = SELF::ERRORS[$function][$response['status_code']];
        } else {
            $message = "Unknown Error";
        }

        $logForDB['message'] = $message;
        
        Log::channel($log)->debug("$time Status Message: $message");

        $logForDB['status'] = ($response['status_code'] == 00) ? ModelsLog::STATUS_SUCCESS : ModelsLog::STATUS_ERROR;
        ModelsLog::addLog($logForDB);
        
        return [
            'status' => ($response['status_code'] == 00) ? true : false,
            'status_message' => $message,
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en-US";
        }
        if (request()->lang == "cn") {
            return "zh-CN";
        }
        if (request()->lang == "bm") {
            return "id-ID";
        }
        return "en-US";
    }

}