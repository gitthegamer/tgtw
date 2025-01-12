<?php

namespace App\Modules;

use Exception;
use App\Models\Log as ModelsLog;

class _DreamingController
{

    const ERRORS = [
        0 => "Success",
        3 => "Parameter Error",
        4 => "Signature Error",
    ];

    protected $token;
    protected $member;
    protected $random;
    protected $data;
    protected $agentacc;
    protected $lang;
    protected $domains;
    protected $apikey;
    protected $list;
    public static function init($function, $params)
    {
        $controller = new _DreamingController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {

        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->token = $this->encypt_to_token($function);
    }

    public function get_url($function)
    {
        return config('api.DG_LINK') . $function . '/' . $this->agentacc;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "user/signup":
                return [
                    'token' => $this->token,
                    'random' => $this->random,
                    'data' => $this->data,
                    'member' => $this->member
                ];
            case "user/getBalance";
                return [
                    'token' => $this->token,
                    'random' => $this->random,
                    'member' => $this->member
                ];
            case "account/transfer":
                return [
                    'token' => $this->token,
                    'random' => $this->random,
                    'data' => $this->data,
                    'member' => $this->member
                ];
            case "account/checkTransfer":
                return [
                    'token' => $this->token,
                    'random' => $this->random,
                    'data' => $this->data
                ];
            case "user/login":
                return [
                    "token" => $this->token,
                    'random' => $this->random,
                    "lang" => $this->lang,
                    'data' => $this->data,
                    'domains' => $this->domains,
                    "member" => $this->member
                ];
            case "game/updateLimit":
                return [
                    "token" => $this->token,
                    'random' => $this->random,
                    "lang" => $this->lang,
                    'data' => $this->data,
                    'domains' => $this->domains,
                    "member" => $this->member
                ];
            case "game/getReport":
                return [
                    "token" => $this->token,
                    'random' => $this->random,
                ];
            case "game/markReport":
                return [
                    "token" => $this->token,
                    'random' => $this->random,
                    'list' => $this->list
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_DG,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {
                },
                'body' => json_encode($this->make_params($function)),
                // 'query' => $this->make_params($function),
                // 'form_params' => $this->make_params($function),
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

        if ($response['codeId'] !== 0 && $response['codeId'] !== 116) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => ($response['codeId'] == 0 || $response['codeId'] == 116),
            'status_message' => $response['random'] ?? '',
            'data' => $response
        ];
    }

    public function encypt_to_token($function)
    {


        return md5($this->agentacc . $this->apikey . $this->random);
    }

    function hmac_sha1($str, $key)
    {
        $signature = "";
        if (function_exists('hash_hmac')) {
            $signature = base64_encode(hash_hmac("sha1", $str, $key, true));
        } else {
            $blocksize = 64;
            $hashfunc = 'sha1';
            if (strlen($key) > $blocksize) {
                $key = pack('H*', $hashfunc($key));
            }
            $key = str_pad($key, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);
            $hmac = pack(
                'H*',
                $hashfunc(
                    ($key ^ $opad) . pack(
                        'H*',
                        $hashfunc(
                            ($key ^ $ipad) . $str
                        )
                    )
                )
            );
            $signature = base64_encode($hmac);
        }
        return $signature;
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "cn";
        }
        return "en";
    }
}
