<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use DateTime;
use DateTimeZone;
use Exception;

class _AllbetController
{

    protected $agent;
    protected $player;
    protected $pageSize;
    protected $pageIndex;
    protected $recursion;
    protected $players;
    protected $sn;
    protected $type;
    protected $amount;
    protected $hashedBody;
    protected $returnUrl;
    protected $signatureString;
    protected $md5signature;
    protected $startDateTime;
    protected $endDateTime;
    protected $pageNum;
    protected $vipHandicap;
    protected $generalHandicaps;

    const ERRORS = [
        0 => "Success",
        3 => "Parameter Error",
        4 => "Signature Error",
    ];

    public static function init($function, $params)
    {
        $controller = new _AllbetController();
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
        return config('api.AB_LINK') . $function;
    }


    public function make_params($function)
    {
        switch ($function) {
            case "CheckOrCreate":
                return [
                    'agent' => $this->agent,
                    'player' => $this->player,
                ];
            case "GetBalances";
                return [
                    'agent' => $this->agent,
                    'pageSize' => $this->pageSize,
                    "pageIndex" => $this->pageIndex,
                    'recursion' => $this->recursion,
                    'players' => $this->players
                ];
            case "Transfer":
                return [
                    'sn' => $this->sn,
                    'agent' => $this->agent,
                    'player' => $this->player,
                    'type' => $this->type,
                    "amount" => $this->amount,
                ];
            case "ModifyPlayerSetting":
                return [
                    'player' => $this->player,
                    'generalHandicaps' => $this->generalHandicaps,
                ];
            case "GetTransferState":
                return [
                    'sn' => $this->sn,
                ];
            case "Login":
                return [
                    'player' => $this->player,
                    'returnUrl' => $this->returnUrl
                ];
            case "PagingQueryBetRecords":
                return [
                    'startDateTime' => $this->startDateTime,
                    'endDateTime' => $this->endDateTime,
                    'pageNum' => $this->pageNum,
                    'pageSize' => $this->pageSize,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_AllBet,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);
        $this->hashedBody = $this->md5Body($this->make_params($function));
        $date = new DateTime('now', new DateTimeZone('UTC'));
        $formatted_date = $date->format('D, d M Y H:i:s') . ' UTC';
        $this->signatureString = $this->stringForSignature($function, $formatted_date);
        $hashedStringSignature = SELF::hashedSignature();

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "AB " . config('api.AB_API_OPERATOR_ID_LIVE') . ":" . $hashedStringSignature,
                    'Content-MD5' => $this->hashedBody,
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'Date' => $formatted_date,
                ],
                'body' => json_encode($this->make_params($function)),
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

        if (($response['resultCode'] != 'OK' && $response['resultCode'] != 'PLAYER_EXIST')) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => ($response['resultCode'] == 'OK' || $response['resultCode'] == 'PLAYER_EXIST'),
            'status_message' => $response['message'] ?? "no message",
            'data' => $response
        ];
    }

    public function md5Body($params)
    {
        $output = json_encode($params);
        return base64_encode(md5($output, true));
    }

    public function stringForSignature($function, $date)
    {
        return "POST" . "\n" . $this->hashedBody . "\n" . "application/json; charset=UTF-8" . "\n" . $date . "\n" . "/" . $function;
    }

    public function hashedSignature()
    {
        $result = base64_encode(hash_hmac('sha1', mb_convert_encoding($this->signatureString, 'UTF-8', 'ISO-8859-1'), base64_decode(config('api.AB_KEY_LIVE')), true));
        return $result;
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
