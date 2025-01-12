<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class _EvolutionController
{

    const ERRORS = [
        0 => "Success",
        3 => "Parameter Error",
        4 => "Signature Error",
    ];

    protected $cCode;
    protected $ecID;
    protected $euID;
    protected $amount;
    protected $eTransID;
    protected $createuser;
    protected $output;
    protected $uuid;
    protected $player_id;
    protected $player_update;
    protected $player_firstName;
    protected $player_lastName;
    protected $player_country;
    protected $player_language;
    protected $player_currency;
    protected $session_ip;
    protected $session_id;
    protected $startDate;
    protected $endDate;

    protected $group_id;
    protected $action;



    public static function init($function, $params)
    {
        $controller = new _EvolutionController();
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
        if ($function == 'ua/v1') {
            return config('api.EVOLUTION_HOST_LINK_LIVE') . '/' . $function . '/' . $this->ecID . '/' . config('api.EVOLUTION_API_TOKEN_LIVE');
        } elseif ($function == 'gamehistory') {
            return config('api.EVOLUTION_GAME_HISTORY_LINK_LIVE');
        }
        return config('api.EVOLUTION_HOST_LINK_LIVE') . '/api/ecashier';
    }

    public function make_params($function)
    {
        switch ($function) {
            case "ua/v1":
                return [
                    'uuid' => $this->uuid,
                    'player' => [
                        'id' => $this->player_id,
                        'update' => $this->player_update,
                        'firstName' => $this->player_firstName,
                        'lastName' => $this->player_lastName,
                        'country' => $this->player_country,
                        'language' => $this->player_language,
                        'currency' => $this->player_currency,
                        'session' => [
                            'ip' => $this->session_ip,
                            'id' => $this->session_id,
                        ],
                        'group' => [
                            'id' => $this->group_id,
                            'action' => $this->action,
                        ]
                    ],
                ];
            case "RWA";
                return [
                    'cCode' => $this->cCode,
                    'ecID' => $this->ecID,
                    'euID' => $this->euID,
                    'output' => $this->output,
                ];
            case "EDB":
                return [
                    'cCode' => $this->cCode,
                    'ecID' => $this->ecID,
                    'euID' => $this->euID,
                    'amount' => $this->amount,
                    'eTransID' => $this->eTransID,
                    'output' => $this->output,

                ];
            case "ECR":
                return [
                    'cCode' => $this->cCode,
                    'ecID' => $this->ecID,
                    'euID' => $this->euID,
                    'amount' => $this->amount,
                    'eTransID' => $this->eTransID,
                    'createuser' => $this->createuser,
                    'output' => $this->output,
                ];
            case "TRI":
                return [
                    'cCode' => $this->cCode,
                    'ecID' => $this->ecID,
                    'euID' => $this->euID,
                    'eTransID' => $this->eTransID,
                    'output' => $this->output,
                ];
            case "gamehistory":
                return [
                    'startDate' => $this->startDate,
                    'endDate' => $this->endDate
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_Evolution,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);
        $method = 'GET';
        $header = ['Content-Type' => 'application/json'];
        if ($function == 'ua/v1') {
            $method = 'POST';
        }
        if ($function == 'gamehistory') {
            $header['Authorization'] = SELF::getBearer();
            $logForDB['header'] = $header;
        }

        $logForDB['method'] = $method;

        try {
            $client = new Client(["base_uri" => $this->get_url($function), 'verify' => false]);
            $response = $client->request($method, "", $request = [
                'http_errors' => false,
                'headers'        => $header,
                'on_stats'  => function (\GuzzleHttp\TransferStats $stats) use ($time) {
                },
                ($method == 'POST') ? 'body' : 'query' => ($method == 'POST') ? json_encode($this->make_params($function)) : $this->make_params($function),
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

        if (isset($response['errors'])) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => isset($response['errors']) ? false : true,
            'status_message' => isset($response['errors'])  ? $response['errors'][0]['message'] ?? "no message" : "no message",
            'data' => $response
        ];
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

    public function getBearer()
    {
        $credentials = $this->ecID . ':' . config('api.EVOLUTION_API_TOKEN_LIVE');
        return 'Basic ' . base64_encode($credentials);
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "zh";
        }
        if (request()->lang == "ms") {
            return "ms";
        }

        return "en";
    }
}
