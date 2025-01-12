<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;


class _ApolloController
{
    protected $action;
    protected $ts;
    protected $parent;
    protected $uid;
    protected $name;
    protected $credit_allocate;
    protected $serialNo;
    protected $amount;
    protected $starttime;
    protected $endtime;
    protected $lang;
    protected $mType;
    protected $gType;
    protected $windowMode;
    protected $lobbyURL;
    protected $backBtn;
    protected $gtype;

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }
    public function make_params($function)
    {
        switch ($function) {
            case "Tr_CreateUser.aspx":
                return [
                    'action' => $this->action,
                    'ts' => $this->ts,
                    'parent' => $this->parent,
                    'uid' => $this->uid,
                    'name' => $this->name,
                    'credit_allocate' => $this->credit_allocate,
                ];
            case "Tr_UserInfo.aspx":
                return [
                    'action' => $this->action,
                    'ts' => $this->ts,
                    'parent' => $this->parent,
                    'uid' => $this->uid,
                ];
            case "Tr_ChangeV.aspx":
                return [
                    'action' => $this->action,
                    'ts' => $this->ts,
                    'parent' => $this->parent,
                    'uid' => $this->uid,
                    'serialNo' => $this->serialNo,
                    'amount' => $this->amount,
                ];
            case "Tr_singleV.aspx":
                return [
                    'action' => $this->action,
                    'ts' => $this->ts,
                    'parent' => $this->parent,
                    'serialNo' => $this->serialNo,
                ];
            case "Tr_GameList.aspx":
                return [
                    'action' => $this->action,
                    'ts' => $this->ts,
                    'parent' => $this->parent,
                    'lang' => $this->lang,
                ];
            case "Tr_GetToken.aspx":
                return [
                    'action' => $this->action,
                    'ts' => $this->ts,
                    'uid' => $this->uid,
                    'gType' => $this->gType,
                    'lang' => $this->lang,
                    'windowMode' => $this->windowMode,
                    'lobbyURL' => $this->lobbyURL,
                    'backBtn' => $this->backBtn,
                ];
            case "Tr_QueryGameJsonResult.aspx":
                return [
                    'action' => $this->action,
                    'ts' => $this->ts,
                    'parent' => $this->parent,
                    'uid' => $this->uid,
                    'starttime' => $this->starttime,
                    'endtime' => $this->endtime,
                    'lang' => $this->lang,
                    'gtype' => $this->gtype,
                ];
            default:
                return [];
        }
    }

    public function get_url($function)
    {
        return config('api.APOLLO_LINK') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _ApolloController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "post";

        $logForDB = [
            'channel' => ModelsLog::CHANNEL_APOLLO,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'Apollo_api_records';
        if ($function === "Tr_QueryGameJsonResult.aspx") {
            $log = 'Apollo_api_ticket_records';
        } elseif ($function === "Tr_ChangeV.aspx" || $function === "Tr_singleV.aspx") {
            $log = 'Apollo_api_transfer_records';
        } elseif ($function === "Tr_UserInfo.aspx") {
            $log = 'Apollo_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);

        $this->create_param($function, $params);
        $params = $this->make_params($function);
        Log::channel($log)->debug("$time Params : " . json_encode($params));
        $encrypt_data = $this->encrytData($params);
        $dc = config("api.APOLLO_DC");
        $data = [
            'dc' => $dc,
            'x' => $encrypt_data
        ];
        $url = $this->get_url($function);
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->$method($url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $data,
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
            ]);
            $response = @json_decode($response->getBody()->getContents(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
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
        if ($response['status'] != "0000") {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }
        
        return [
            'status' => ($response['status'] == "0000"),
            'status_message' => "",
            'data' => $response
        ];
    }

    public function encrytData($str)
    {
        $json_str = json_encode($str);
        $str = $this->padString($json_str);
        $cipher_algo = "AES-128-CBC";
        $key = config('api.APOLLO_KEY');
        $iv = config('api.APOLLO_IV');
        $encrypted_string = openssl_encrypt(
            $str,
            $cipher_algo,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        $data = base64_encode($encrypted_string);
        $data = str_replace(["+", "/", "="], ["-", "_", ""], $data);
        return $data;
    }

    public function padString($source)
    {
        $paddingChar = "\0";
        $size = 16;
        $x = strlen($source) % $size;
        $padLength = $size - $x;
        for ($i = 0; $i < $padLength; $i++) {
            $source .= $paddingChar;
        }
        return $source;
    }
    public static function getLocale()
    {
        if (request()->lang == "en") {
            return 'en';
        }
        if (request()->lang == "cn") {
            return 'zh';
        }
        return 'en';
    }
}
