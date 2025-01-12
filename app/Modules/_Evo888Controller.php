<?php

namespace App\Modules;

use Exception;
use App\Models\Log as ModelsLog;

class _Evo888Controller
{
    const ERRORS = [
        0 => "Success",
        3 => "Parameter Error",
        4 => "Signature Error",
    ];

    protected $action;
    protected $username;
    protected $name;
    protected $passwd;
    protected $authcode;
    protected $time;
    protected $tel;
    protected $type;
    protected $score;
    protected $sign;
    protected $desc;
    protected $sdate;
    protected $edate;
    protected $gametype;

    public static function init($function, $params)
    {
        $controller = new _Evo888Controller();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->sign = $this->encypt_to_token($function);
    }

    public function get_url($function)
    {
        return config('api.EVO888_LINK');
    }

    public function make_params($function)
    {
        switch ($function) {
            case "addUser":
                return [
                    'action' => $this->action,
                    'name' => $this->name,
                    'passwd' => $this->passwd,
                    'authcode' => $this->authcode,
                    'time' => $this->time,
                    'tel' => $this->tel,
                    'type' => $this->type,
                    'sign' => $this->sign,
                    'desc' => $this->desc
                ];
            case "searchUser";
                return [
                    'action' => $this->action,
                    'username' => $this->username,
                    'time' => $this->time,
                    'authcode' => $this->authcode,
                    'type' => $this->type,
                    'sign' => $this->sign
                ];
            case "setScore":
                return [
                    'action' => $this->action,
                    'username' => $this->username,
                    'time' => $this->time,
                    'score' => $this->score,
                    'authcode' => $this->authcode,
                    'type' => $this->type,
                    'sign' => $this->sign
                ];
            case "getUserGameLog":
                return [
                    'action' => $this->action,
                    'time' => $this->time,
                    'username' => $this->username,
                    'sdate' => $this->sdate,
                    'edate' => $this->edate,
                    'authcode' => $this->authcode,
                    'gametype' => $this->gametype,
                    'sign' => $this->sign
                ];
            case "getTotalReport":
                return [
                    'action' => $this->action,
                    'time' => $this->time,
                    'type' => $this->type,
                    'sdate' => $this->sdate,
                    'edate' => $this->edate,
                    'authcode' => $this->authcode,
                    'sign' => $this->sign
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_Evo888,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {
                },
                'query' => $this->make_params($function),
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

        if ($response['code'] != 0) {
            if ($function == "getTotalReport" && $response['code'] == -1) {
            } else {
                $logForDB['status'] = ModelsLog::STATUS_ERROR;
                ModelsLog::addLog($logForDB);
            }
        }

        return [
            'status' => $response['code'] == 0,
            'status_message' => $response['msg']  ?? "no message",
            'data' => $response
        ];
    }

    public function encypt_to_token($function)
    {
        return md5(strtolower($this->authcode . $this->username . $this->time . config('api.EVO888_SECRET_KEY')));
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
            return "zh";
        }
        return "en";
    }
}
