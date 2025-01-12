<?php

namespace App\Modules;

use Exception;
use App\Models\Log as ModelsLog;

class _DragonsoftController
{
    protected $agent;
    protected $account;
    protected $password;
    protected $serial;
    protected $amount;
    protected $oper_type;
    protected $finish_time;
    protected $index;
    protected $limit;
    protected $a;
    protected $s;
    protected $game_id;
    protected $lang;
    protected $backurl;



    const ERRORS = [
        1 => "Success",
    ];


    public static function init($function, $params)
    {
        $controller = new _DragonsoftController();
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
        return config('api.DS_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "member/create":
                return [
                    'agent' => $this->agent,
                    'account' => $this->account,
                    "password" => $this->password,
                ];
            case "trans/check_balance":
                return [
                    "agent" => $this->agent,
                    'account' => $this->account,
                ];
            case "trans/transfer":
                return [
                    'serial' => $this->serial,
                    'agent' => $this->agent,
                    'account' => $this->account,
                    'amount' => $this->amount,
                    'oper_type' => $this->oper_type,
                ];
            case "trans/verify":
                return [
                    'agent' => $this->agent,
                    'account' => $this->account,
                    "serial" => $this->serial,
                ];
            case "member/login_game":
                return [
                    'game_id' => $this->game_id,
                    'agent' => $this->agent,
                    'account' => $this->account,
                    'lang' => $this->lang,
                    'backurl' => $this->backurl,
                ];
            case "config/get_game_info_state_list":
                return [];
            case "record/get_bet_records":
                return [
                    'finish_time' => $this->finish_time,
                    'index' => $this->index,
                    'limit' => $this->limit
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_DS,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);

        $a = $this->AESKeyEncryption(json_encode($this->make_params($function)), config('api.DS_AES'));
        $s = $this->SignKeyEncryption($a);
        $data = ["channel" => config('api.DS_CHANNEL'), "data" => $a, "sign" => $s];

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {
                },
                'body' => json_encode($data),
                // 'form_params' => $data,
                // 'query' => $this->make_params($function),
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
                'status_message' => "Connection Error",
                'data' => null,
            ];
        }

        if ($response['result']['code'] !== 1) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => $response['result']['code'] == 1,
            'status_message' => $response['result']['msg'] ?? "no message",
            'data' => $response
        ];
    }
    public function AESKeyEncryption($d, $a)
    {
        $salt = openssl_random_pseudo_bytes(8);
        $salted = $dx = '';
        while (strlen($salted) < 48) {
            $dx = md5($dx . $a . $salt, true);
            $salted .= $dx;
        }
        $key = substr($salted, 0, 32);
        $iv = substr($salted, 32, 16);
        return base64_encode('Salted__' . $salt . openssl_encrypt($d . '', 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv));
    }

    public function SignKeyEncryption($a)
    {
        return md5($a . config('api.DS_SIGN_KEY'), false);
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en_us";
        }
        if (request()->lang == "cn") {
            return "zh_cn";
        }
        return "en_us";
    }
}
