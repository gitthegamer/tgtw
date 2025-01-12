<?php

namespace App\Modules;

use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _SAGamingController
{
    const API_URL = "http://api.sa-apisvr.com/api/api.aspx";
    const LAUNCH_URL = "https://web.sa-globalxns.net/app.aspx";
    const BETDETAIL_URL = "http://api.sa-rpt.com/api/api.aspx";

    protected $method;
    protected $key;
    protected $time;
    protected $username;
    protected $currencyType;
    protected $orderId;
    protected $amount;
    protected $md5_key;
    protected $encrypt_key;
    protected $md5;
    protected $des;
    protected $blimit;
    protected $date;

    const ERROR_ARRAYS = [
        "0" => "Success",
        "108" => "Username length/format incorrect",
        "113" => "Username duplicated",
        "114" => "Currency not exist",
        "133" => "Create user failed",
    ];


    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->des = $this->encypt_to_des($function);
        $this->md5 = $this->encypt_to_md5($function);
    }

    public function make_params($function)
    {
        if ($function == "RegUserInfo") {
            return [
                'q' => $this->des,
                's' => $this->md5,
            ];
        }
        if ($function == "GetUserStatusDV") {
            return [
                'q' => $this->des,
                's' => $this->md5,
            ];
        }
        if ($function == "DebitBalanceDV") {
            return [
                'q' => $this->des,
                's' => $this->md5,
            ];
        }
        if ($function == "CreditBalanceDV") {
            return [
                'q' => $this->des,
                's' => $this->md5,
            ];
        }
        if ($function == "CheckOrderDetailsDV") {
            return [
                'q' => $this->des,
                's' => $this->md5,
            ];
        }
        if ($function == "LoginRequest") {
            return [
                'q' => $this->des,
                's' => $this->md5,
            ];
        }
        if ($function == "SetBetLimit") {
            return [
                'q' => $this->des,
                's' => $this->md5,
            ];
        }
        if ($function == "GetAllBetDetailsDV") {
            return [
                'q' => $this->des,
                's' => $this->md5,
            ];
        }
    }

    public function encypt_to_des($function)
    {
        return base64_encode(openssl_encrypt($this->query_string($function), 'DES-CBC', $this->encrypt_key, OPENSSL_RAW_DATA, $this->encrypt_key));
    }

    public function query_string($function)
    {
        if ($function == "RegUserInfo") {
            $query = [
                'method' => 'RegUserInfo',
                'Key' => $this->key,
                'Time' => $this->time,
                'Username' => $this->username,
                'CurrencyType' => $this->currencyType,
            ];
            return http_build_query($query);
        }
        if ($function == "GetUserStatusDV") {
            $query = [
                'method' => 'GetUserStatusDV',
                'Key' => $this->key,
                'Time' => $this->time,
                'Username' => $this->username,
            ];
            return http_build_query($query);
        }
        if ($function == "DebitBalanceDV") {
            $query = [
                'method' => 'DebitBalanceDV',
                'Key' => $this->key,
                'Time' => $this->time,
                'Username' => $this->username,
                'OrderId' => "OUT$this->time$this->username",
                'DebitAmount' => $this->amount,
            ];
            return http_build_query($query);
        }
        if ($function == "CreditBalanceDV") {
            $query = [
                'method' => 'CreditBalanceDV',
                'Key' => $this->key,
                'Time' => $this->time,
                'Username' => $this->username,
                'OrderId' => "IN$this->time$this->username",
                'CreditAmount' => $this->amount,
            ];
            return http_build_query($query);
        }
        if ($function == "CheckOrderDetailsDV") {
            $query = [
                'method' => 'CheckOrderDetailsDV',
                'Key' => $this->key,
                'Time' => $this->time,
                'OrderId' => $this->orderId,
            ];
            return http_build_query($query);
        }
        if ($function == "LoginRequest") {
            $query = [
                'method' => 'LoginRequest',
                'Key' => $this->key,
                'Time' => $this->time,
                'Username' => $this->username,
                'CurrencyType' => $this->currencyType,
            ];
            return http_build_query($query);
        }
        if ($function == "SetBetLimit") {
            $query = [
                'method' => 'SetBetLimit',
                'Key' => $this->key,
                'Time' => $this->time,
                'Username' => $this->username,
                'Currency' => $this->currencyType,
                'Set1' => $this->blimit,
            ];
            return http_build_query($query);
        }
        if ($function == "GetAllBetDetailsDV") {
            $query = [
                'method' => 'GetAllBetDetailsDV',
                'Key' => $this->key,
                'Time' => $this->time,
                'Date' => $this->date,
            ];
            return http_build_query($query);
        }
    }

    public function encypt_to_md5($function)
    {
        return strtolower(md5($this->encypt_md5($function)));
    }

    public function encypt_md5($function)
    {
        if ($function == "RegUserInfo") {
            return $this->query_string($function) . $this->md5_key . $this->time . $this->key;
        }
        if ($function == "GetUserStatusDV") {
            return $this->query_string($function) . $this->md5_key . $this->time . $this->key;
        }
        if ($function == "DebitBalanceDV") {
            return $this->query_string($function) . $this->md5_key . $this->time . $this->key;
        }
        if ($function == "CreditBalanceDV") {
            return $this->query_string($function) . $this->md5_key . $this->time . $this->key;
        }
        if ($function == "CheckOrderDetailsDV") {
            return $this->query_string($function) . $this->md5_key . $this->time . $this->key;
        }
        if ($function == "LoginRequest") {
            return $this->query_string($function) . $this->md5_key . $this->time . $this->key;
        }
        if ($function == "SetBetLimit") {
            return $this->query_string($function) . $this->md5_key . $this->time . $this->key;
        }
        if ($function == "GetAllBetDetailsDV") {
            return $this->query_string($function) . $this->md5_key . $this->time . $this->key;
        }
    }

    public static function generateLogin($username, $token, $lobby, $isMobile)
    {
        return SELF::LAUNCH_URL . "?username=$username&token=$token&lobby=$lobby&mobile=$isMobile";
    }

    public static function init($function, $params)
    {
        $controller = new _SAGamingController();
        return $controller->request($function, $params);
    }

    public function get_url($function)
    {
        if($function == "GetAllBetDetailsDV"){
            return SELF::BETDETAIL_URL;
        }
        return SELF::API_URL;
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "GET";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_SAGAMING,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
        

        $log = 'SA_api_records';
        if ($function == "GetAllBetDetailsDV") {
            $log = 'SA_api_ticket_records';
        }
        if ($function == "DebitBalanceDV" || $function == "CreditBalanceDV" || $function == "CheckOrderDetailsDV") {
            $log = 'SA_api_transfer_records';
        }
        if ($function == "GetUserStatusDV") {
            $log = 'SA_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);
        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        Log::channel($log)->debug("$time Query : " . json_encode($this->query_string($function)));

        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
            ]);
            $response = $client->get($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'query' => $this->make_params($function),
                'http_errors' => false,
            ]);
            $response = @json_decode(json_encode(simplexml_load_string($response->getBody())), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            Log::channel($log)->debug("$time Response: " . json_encode($response));
        } catch (\Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            return [
                'status' => false,
                'message' => "Unknown ERROR",
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
                'status_message' => SELF::ERROR_ARRAYS['ErrorMsgId'] ?? "Unknown ERROR",
                'data' => null,
            ];
        }

        if (isset($response['ErrorMsgId']) && $response['ErrorMsgId'] != "0") {
            $logForDB["message"] = SELF::ERROR_ARRAYS[$response['ErrorMsgId']] ?? "Unknown Error";
            Log::channel($log)->debug("$time Status: Error");
        } else {
            Log::channel($log)->debug("$time Status: Success");
        }

        if ($function == "GetAllBetDetailsDV") {
            if(($response['ErrorMsgId'] !== "0")){
                $logForDB['status'] = ModelsLog::STATUS_ERROR;
                ModelsLog::addLog($logForDB);
            }
        

            return [
                'status' => ($response['ErrorMsgId'] == "0") ? true : false,
                'status_message' => SELF::ERROR_ARRAYS[$response['ErrorMsgId']] ?? "Unknown Error",
                'data' => $response['BetDetailList'] ?? [],
            ];
        }

        if($response['ErrorMsgId'] !== "0"){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => ($response['ErrorMsgId'] == "0") ? true : false,
            'status_message' => SELF::ERROR_ARRAYS[$response['ErrorMsgId']] ?? "Unknown Error",
            'data' => $response
        ];
    }
}
