<?php

namespace App\Modules;

use GuzzleHttp\Exception\RequestException;
use App\Models\Log as ModelsLog;
use Exception;

class _AdvantPlayController
{

    const ERRORS = [
        0 => "Success",
        3 => "Parameter Error",
        4 => "Signature Error",
    ];

    protected $Timestamp;
    protected $Seq;
    protected $PlayerId;
    protected $PlayerName;
    protected $Currency;
    protected $OPToken;
    protected $OPTransferID;
    protected $Amount;
    protected $DateFrom;
    protected $DateTo;
    protected $Page;
    protected $Size;
    protected $Token;
    protected $GameCode;
    protected $LangCode;
    protected $LaunchLobby;

    public static function init($function, $params)
    {
        $controller = new _AdvantPlayController();
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
        return config('api.ADVANT_PLAY_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "CreatePlayer":
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq' => $this->Seq,
                    'PlayerId' => $this->PlayerId,
                    'PlayerName' => $this->PlayerName,
                    "BrandCode" => "default",
                    "SiteCode" => "default",
                    'Currency' => $this->Currency,
                ];
            case "GetBalance";
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq' => $this->Seq,
                    'OPToken' => $this->OPToken,
                    'PlayerId' => $this->PlayerId,
                    "BrandCode" => "default",
                    "SiteCode" => "default",
                ];
            case "TransferIn":
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq' => $this->Seq,
                    'PlayerId' => $this->PlayerId,
                    "OPTransferID" => $this->OPTransferID,
                    "Currency" => $this->Currency,
                    "Amount" => $this->Amount,
                    "BrandCode" => "default",
                    "SiteCode" => "default",
                ];
            case "TransferOut":
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq' => $this->Seq,
                    'PlayerId' => $this->PlayerId,
                    "OPTransferID" => $this->OPTransferID,
                    "Currency" => $this->Currency,
                    "Amount" => $this->Amount,
                    "BrandCode" => "default",
                    "SiteCode" => "default",
                ];
            case "CheckTransfer":
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq' => $this->Seq,
                    "BrandCode" => "default",
                    "SiteCode" => "default",
                    "OPTransferID" => $this->OPTransferID,
                ];
            case "GetGameList":
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq' => $this->Seq,
                    "BrandCode" => "default",
                    "SiteCode" => "default",
                    "Size" => $this->Size
                ];
            case "GetPlayerToken":
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq'       => $this->Seq,
                    'PlayerId'  => $this->PlayerId,
                    "BrandCode" => "default",
                    "SiteCode"  => "default",
                ];
            case "GetLaunchURL":
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq'       => $this->Seq,
                    'PlayerId'  => $this->PlayerId,
                    "BrandCode" => "default",
                    "SiteCode"  => "default",
                    "Token"  => $this->Token,
                    "GameCode"  => $this->GameCode,
                    "LangCode"  => $this->LangCode,
                    "LaunchLobby"  => $this->LaunchLobby,
                ];
            case "GetBatchHistory":
                return [
                    'Timestamp' => $this->Timestamp,
                    'Seq'       => $this->Seq,
                    'BrandCode' => 'default',
                    'SiteCode' => 'default',
                    'DateFrom' => $this->DateFrom,
                    'DateTo' => $this->DateTo,
                    'Page' => $this->Page,
                    'PageSize' => 1000,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_AP,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       
        $this->create_param($function, $params);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {
                },
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

        if($response['ErrorCode'] != 0) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

       

        return [
            'status' => $response['ErrorCode'] == 0,
            'status_message' => $response['ErrorDescription'] != 0  ?? "no message",
            'data' => $response
        ];
    }

    public function encypt_to_token($function)
    {
        return $this->encypt_string($function);
    }
    public function encypt_string($function)
    {
        return md5(config('api.ADVANT_PLAY_SECRET_KEY') . json_encode($this->make_params($function)));
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en-US";
        }
        if (request()->lang == "cn") {
            return "zh-CN";
        }
        return "en-US";
    }
}
