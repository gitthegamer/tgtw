<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _888kingController2
{
    const ERROR_ARRAYS = [
        "api/user/balance" => [
            0 => "Success",
            1 => "Invalid Member ID",
            2 => "Invalid Host ID",
        ],
        "api/user/create" => [
            0 => "Success",
            1 => "Invalid Member ID",
            2 => "Invalid Host ID",
            3 => "Invalid Currency",
        ],
        "api/user/deposit-v2" => [
            0 => "Success",
            1 => "Invalid Member ID",
            2 => "Invalid Host ID",
            5 => "Invalid transid",
            6 => "This transid has been used",
        ],
        "api/user/withdraw-v2" => [
            0 => "Success",
            1 => "Invalid Member ID",
            2 => "Invalid Host ID",
            3 => "Insufficient Funds",
            5 => "Invalid transid",
            6 => "This transid has been used",
        ],
        "api/report" => [
            0 => "Success",
            2 => "Invalid Host ID",
        ],
        "api/user/gamelist" => [
            0 => "Success",
            2 => "Invalid Host ID",
            2001 => "Required field cannot be empty",
        ],
        "api/user/generate-access-token" => [
            0 => "Success",
            1001 => "Invalid Host ID",
            1002 => "Invalid Member ID",
            1003 => "User does not exist",
        ],
    ];

    protected $host_id;
    protected $member_id;
    protected $currency;
    protected $amount;
    protected $transid;
    protected $page_size;
    protected $key;
    protected $trans_id;

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function make_params($function)
    {
        if ($function == "api/user/balance") {
            return [
                'host_id' => $this->host_id,
                'member_id' => $this->member_id,
            ];
        }

        if ($function == "api/user/create") {
            return [
                'host_id' => $this->host_id,
                'member_id' => $this->member_id,
                'currency' => $this->currency,
            ];
        }

        if ($function == "api/user/deposit-v2") {
            return [
                'host_id' => $this->host_id,
                'member_id' => $this->member_id,
                'amount' => $this->amount,
                'transid' =>  $this->transid,
            ];
        }

        if ($function == "api/user/withdraw-v2") {
            return [
                'host_id' => $this->host_id,
                'member_id' => $this->member_id,
                'amount' => $this->amount,
                'transid' =>  $this->transid,
            ];
        }

        if ($function == "api/report") {
            return [
                'host_id' => $this->host_id,
                'key' => $this->key,
                'page_size' => $this->page_size
            ];
        }
        if ($function == "api/user/gamelist") {
            return [
                'host_id' => $this->host_id,
            ];
        }
        if ($function == "api/user/generate-access-token") {
            return [
                'host_id' => $this->host_id,
                'member_id' => $this->member_id,
            ];
        }

        if ($function == "api/user/wallet-trans-status") {
            return [
                'host_id' => $this->host_id,
                'trans_id' => $this->trans_id,
            ];
        }
    }

    public function get_url($function)
    {
        if ($function == 'api/report') {
            return config('api.888KING_PRODUCT_LINK') . $function;
        }
        return config('api.888KING_LINK_LIVE') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _888kingController2();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "GET";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_888King,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $this->create_param($function, $params);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {},
                'query' => $this->make_params($function),
                'timeout' => $function == "api/report" ? 0 : 7, // 如果是 api/report，超时无限制，否则为7秒
            ]);

            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);

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
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }

        if (isset($response['error'])) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);

            return [
                'status' => false,
                'status_message' => $response['error']['message'],
                'data' => $response
            ];
        }

        if (!isset($response['data'])) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);

            return [
                'status' => false,
                'status_message' => SELF::ERROR_ARRAYS[$function][$response['data']['status_code']] ?? "Data Missing",
                'data' => null,
            ];
        }

        if ($response['data']['status_code'] != 0) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => $response['data']['status_code'] == 0 ? true : false,
            'status_message' => SELF::ERROR_ARRAYS[$function][$response['data']['status_code']] ?? "Unknown Error",
            'data' => $response['data']
        ];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "ch";
        }
        return "en";
    }
}
