<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use App\Models\Log as ModelsLog;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;

class _Rich88Controller
{
    protected $account;
    protected $transfer_no;
    protected $transfer_type;
    protected $amount;
    protected $from;
    protected $to;
    protected $size;
    protected $timestamp;
    protected $api_key;

    public static function init($function, $params)
    {
        $controller = new _Rich88Controller();
        return $controller->request($function, $params);
    }
    public static function timestamp()
    {
        $date = Carbon::now();
        return $date->timestamp * 1000;
    }

    public function get_url($function)
    {
        if ($function == "GetBalance") {
            return config('api.RICH88_LINK') . "v2/platform/balance/";
        } elseif ($function == 'GetTransactionStatus') {
            return config('api.RICH88_LINK') . "v2/platform/transfer/";
        } elseif ($function == 'GetBetLogs') {
            return config('api.RICH88_LINK') . "v2/platform/bet_records?";
        } elseif ($function == 'Login') {
            return config('api.RICH88_LINK') . "v2/platform/login";
        }

        return config('api.RICH88_LINK') . "v2/platform/transfer";
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function make_params($function)
    {
        if ($function == "Login" || $function == "GetBalance") {
            return [
                'account' => $this->account,
            ];
        }
        // This is for deposit/withdrawal
        if ($function == "Transfer") {
            return [
                'account' => $this->account,
                'transfer_no' => $this->transfer_no,
                'transfer_type' => $this->transfer_type,
                'amount' => $this->amount,
            ];
        }
        // This is for get transfer status
        if ($function == "GetTransactionStatus") {
            return [
                'transfer_no' => $this->transfer_no,
            ];
        }
        if ($function == "GetBetLogs") {
            return [
                'from' => $this->from,
                'to' => $this->to,
            ];
        }

        Log::error("Unsupported function: $function");
        return null;
    }
    public function request($function, $params)
    {
        $time = time();
        $apiKey = $this->encrytData();
        $method = ($function == "GetBalance" || $function == "GetTransactionStatus" || $function == "GetBetLogs") ? "get" : "post";
        $log_id = ModelsLog::addLog([
            'channel' => ModelsLog::CHANNEL_RICH88,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ]);
        $logForDB = ['id' => $log_id];

        $log = 'Rich88_api_records';
        if ($function == "GetBetLogs") {
            $log = 'Rich88_api_ticket_records';
        }
        if ($function == "Transfer" || $function == "GetTransactionStatus") {
            $log = 'Rich88_api_transfer_records';
        }
        if ($function == "GetBalance") {
            $log = 'Rich88_api_balance_records';
        }

        Log::channel($log)->debug("$time Function: " . $function);
        Log::channel($log)->debug("$time Params: " . json_encode($params));
        $this->create_param($function, $params);
        $params = $this->make_params($function);
        $url = $this->get_url($function);
        if ($method == 'get') {
            if ($function == 'GetBetLogs') {
                $url .= http_build_query($params);
            } else {
                $url .= implode('/', array_values($params));
            }
        }
        Log::channel($log)->debug("$time URL: " . $url);

        try {
            $client = new Client();
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'api_key' => $apiKey,
                    'pf_id' => config('api.RICH88_PF_ID'),
                    'timestamp' => self::timestamp(),
                ],
            ];

            if ($method == 'get') {
                $options['query'] = $params;
                $response = $client->get($url, $options);
            } else {
                $options['json'] = $params;
                $response = $client->post($url, $options);
            }

            $response = @json_decode($response->getBody()->getContents(), true);
            $status = ($response['code'] === 0);
            Log::channel($log)->debug("$time Response Body: " . json_encode($response));
            $logForDB['status'] = $status ? ModelsLog::STATUS_SUCCESS : ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = json_encode($response);
            ModelsLog::addLog($logForDB);
            if ($function == "Login") {
                return [
                    'status' => $status,
                    'status_message' => "",
                    'data' => $response['data']['url']
                ];
            }

            return [
                'status' => $status,
                'status_message' => "",
                'data' => $response
            ];
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time Unknown ERROR: " . $e->getMessage());
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }
    }

    public function encrytData()
    {
        $PFID = config('api.RICH88_PF_ID');
        $PrivateKey = config('api.RICH88_KEY');
        $timestamp = self::timestamp();
        $combinedString = $PFID . $PrivateKey . $timestamp;
        $apiKey = hash('sha256', $combinedString);
        return $apiKey;
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return 'en-US';
        }
        if (request()->lang == "cn") {
            return 'zh-CN';
        }
        return 'en-US';
    }
}
