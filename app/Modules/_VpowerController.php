<?php

namespace App\Modules;

use Exception;
use App\Models\Log as ModelsLog;


class _VpowerController
{
    const ERRORS = [
        0 => "Success",
        3 => "Parameter Error",
        4 => "Signature Error",
    ];



    protected $Timestamp;
    protected $Username;
    protected $AppId;
    protected $Signature;
    protected $RequestID;
    protected $Amount;
    protected $date;
    protected $PageIndex;
    protected $BeginTime;
    protected $EndTime;


    public static function init($function, $params)
    {
        $controller = new _VpowerController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->Signature = $this->encypt_to_token($function);
    }

    public function get_url($function)
    {
        return config('api.VPOWER_LINK_LIVE');
    }

    public function make_params($function)
    {
        switch ($function) {
            case "getacc":
                return [
                    'Act' => $function,
                    'Timestamp' => $this->Timestamp,
                    'Username' => $this->Username,
                    'AppId' => $this->AppId,
                    'Signature' => $this->Signature
                ];
            case "getbal";
                return [
                    'Act' => $function,
                    'Timestamp' => $this->Timestamp,
                    'Username' => $this->Username,
                    "AppId" => $this->AppId,
                    'Signature' => $this->Signature
                ];
            case "signout";
                return [
                    'Act' => $function,
                    'Timestamp' => $this->Timestamp,
                    'Username' => $this->Username,
                    "AppId" => $this->AppId,
                    'Signature' => $this->Signature
                ];
            case "creditxf":
                return [
                    'Act' => $function,
                    'RequestID' => $this->RequestID,
                    'Timestamp' => $this->Timestamp,
                    'Username' => $this->Username,
                    'AppId' => $this->AppId,
                    "Amount" => $this->Amount,
                    'Signature' => $this->Signature
                ];
            case "creditcheck":
                return [
                    'Act' => $function,
                    'Timestamp' => $this->Timestamp,
                    'RequestID' => $this->RequestID,
                    "AppId" => $this->AppId,
                    'Signature' => $this->Signature,
                    'Username' => '',
                    'BeginTime' => '',
                    'EndTime' => ''
                ];
            case "glog":

                return [
                    'Act' => $function,
                    'Timestamp' => $this->Timestamp,
                    'BeginTime' => $this->BeginTime,
                    'EndTime' => $this->EndTime,
                    'PageIndex' => $this->PageIndex,
                    'AppId' => $this->AppId,
                    'Signature' => $this->Signature
                ];
            case "gamelist":
                return [
                    'Act' => $function,
                    'Timestamp' => $this->Timestamp,
                    'AppId' => $this->AppId,
                    'Signature' => $this->Signature
                ];
            case "tkreq":
                return [
                    'Act' => $function,
                    'Timestamp' => $this->Timestamp,
                    'Username' => $this->Username,
                    'AppId' => $this->AppId,
                    'Signature' => $this->Signature
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_VP,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {},
                'form_params' => $this->make_params($function),
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

        if ($response['ErrorCode'] != 0 && $response['ErrorCode'] != 1 && $response['ErrorCode'] != 12 && $response['ErrorCode'] != 15) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => ($response['ErrorCode'] == 0 || $response['ErrorCode'] == 12 || $response['ErrorCode'] != 15) ? true : false,
            'status_message' => $response['ErrorCode'] != 0  ? "" : "no message",
            'data' => $response
        ];
        //SELF::ERRORS[$response['ErrorCode']]
    }

    public function encypt_to_token($function)
    {
        return $this->encypt_string($function);
    }
    public function encypt_string($function)
    {
        if ($function == "gamelist") {
            return $this->hmac_sha1("Act=" . $function . "&Timestamp=" . $this->Timestamp . "&AppId=" . $this->AppId, config('api.VPOWER_SIGNATURE_KEY_LIVE'));
        }
        if ($function == "getacc") {
            return $this->hmac_sha1("Act=" . $function . "&Timestamp=" . $this->Timestamp . "&Username=" . $this->Username . "&AppId=" . $this->AppId, config('api.VPOWER_SIGNATURE_KEY_LIVE'));
        }
        if ($function == "getbal") {
            return $this->hmac_sha1("Act=" . $function . "&Timestamp=" . $this->Timestamp . "&Username=" . $this->Username . "&AppId=" . $this->AppId, config('api.VPOWER_SIGNATURE_KEY_LIVE'));
        }
        if ($function == "signout") {
            return $this->hmac_sha1("Act=" . $function . "&Timestamp=" . $this->Timestamp . "&Username=" . $this->Username . "&AppId=" . $this->AppId, config('api.VPOWER_SIGNATURE_KEY_LIVE'));
        }
        if ($function == "creditxf") {
            return $this->hmac_sha1("Act=" . $function . "&RequestID=" . $this->RequestID . "&Timestamp=" . $this->Timestamp . "&Username=" . $this->Username . "&AppId=" . $this->AppId . "&Amount=" . $this->Amount, config('api.VPOWER_SIGNATURE_KEY_LIVE'));
        }
        if ($function == "creditcheck") {
            return $this->hmac_sha1("Act=" . $function . "&RequestID=" . $this->RequestID . "&Username=&BeginTime=&EndTime=&Timestamp=" . $this->Timestamp . "&AppId=" . $this->AppId, config('api.VPOWER_SIGNATURE_KEY_LIVE'));
        }
        if ($function == "glog") {
            return $this->hmac_sha1("Act=" . $function . "&GameId=&Username=" . "&BeginTime=" . $this->BeginTime . "&EndTime=" . $this->EndTime .
                "&PageIndex=" . $this->PageIndex . "&Timestamp=" . $this->Timestamp . "&AppId=" . $this->AppId, config('api.VPOWER_SIGNATURE_KEY_LIVE'));
        }
        if ($function == "tkreq") {
            return $this->hmac_sha1("Act=" . $function . "&Timestamp=" . $this->Timestamp . "&Username=" . $this->Username . "&AppId=" . $this->AppId, config('api.VPOWER_SIGNATURE_KEY_LIVE'));
        }
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
