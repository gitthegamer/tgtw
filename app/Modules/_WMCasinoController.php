<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _WMCasinoController
{

    const ERRORS = [
        "MemberRegister" => [
            0 => "Success",
            104 => "New member information error, this account has been used!!",
            10404 => "The account is too long",
            10405 => "Parameter errors: The account is too short",
            10406 => "Parameter errors: The password is too short",
            10407 => "Parameter errors: The note is too long",
            10409 => "Parameter errors: The name is too long",
            10502 => "Parameter errors: The account name must not be blank",
            10508 => "Parameter errors: The password must not be blank",
            10509 => "Parameter errors: The name must not be blank",
            10419 => "Parameter errors: Error chip format (split by comma symbol)",
            10420 => "Parameter errors: Error chip length (between 5-10)",
            10421 => "Parameter errors: Error chip type",
            10422 => "Parameter errors: Account only accepts English, numbers, underscores and @",
            10520 => "Parameter errors: The superior broker only stops using or stops betting"
        ],
        "GetBalance" => [
            0 => "Success",
            103 => "Parameter errors: Agent ID and identifier format error",
            900 => "Parameter errors: No such function",
            911 => "Operation failure: Server is busy",
            10201 => "Parameter errors: This function allows to inquire the report within one day; you have exceeded the maximum number",
            10202 => "Parameter errors: Timestamp is abnormal, more than 30 seconds.",
            10301 => "Parameter errors: Agent ID is null, please check (vendorId)",
            10302 => "Parameter errors: No Agent ID Record",
            10303 => "Parameter errors: Agent exists but agent code (signature) is wrong",
            10304 => "Parameter errors: agent code (signature) is null",
            10411 => "Operation failure: Please try again in 30 seconds.",
            10418 => "Operation failure: Please try again in 10 seconds.",
            10501 => "Parameter errors: No such account, please check it",
            10502 => "Parameter errors: The account name must not be blank",
            10504 => "Parameter errors: This account's password is incorrect",
            10505 => "Parameter errors: Disabled account",
            10507 => "Parameter errors: This account is non-agent referral; this function is not available",
            10512 => "Parameter errors: Account password format is incorrect",
            10601 => "Parameter errors: Limits are not open, please check"
        ],
        "ChangeBalance" => [
            0 => "Success",
            901 => "Operation errors: Turn point failed",
            10410 => "Operation failure: Member transactions on the pen was unsuccessful, please contact customer service staff to unlock",
            10501 => "Operation failure: No such account, please check it",
            10507 => "Operation failure: This account is non-agent referral, this function is not available",
            10801 => "Parameter errors: Changed points must not be 0",
            10802 => "Parameter errors: Changed points is 0, or Parameter is not set",
            10803 => "Parameter errors: Changed points must not be Chinese",
            10804 => "Operation failure: May not repeat the transfer in 5 seconds",
            10805 => "Operation failure: Failure of transferring, the current account has insufficient balance",
            10806 => "Operation failure: Failure of transferring, the account agent has exceeded the credit limit",
            10807 => "Operation failure: Failure of transferring, the ticket number already exists",
            10808 => "Operation failure: The transfer failed, the number of transfers exceeded 10 times in one minute, and the account was locked",
            10810 => "Operation failure: Abnormal connection, transaction unsuccessful"
        ],
        "LogoutGame" => [
            0 => "Success",
            103 => "Parameter errors: Agent ID and identifier format error",
            900 => "Parameter errors: No such function",
            911 => "Operation failure: Server is busy",
            10201 => "Parameter errors: This function allows to inquire the report within one day; you have exceeded the maximum number",
            10202 => "Parameter errors: Timestamp is abnormal, more than 30 seconds.",
            10301 => "Parameter errors: Agent ID is null, please check (vendorId)",
            10302 => "Parameter errors: No Agent ID Record",
            10303 => "Parameter errors: Agent exists but agent code (signature) is wrong",
            10304 => "Parameter errors: agent code (signature) is null",
            10411 => "Operation failure: Please try again in 30 seconds.",
            10418 => "Operation failure: Please try again in 10 seconds.",
            10501 => "Parameter errors: No such account, please check it",
            10502 => "Parameter errors: The account name must not be blank",
            10504 => "Parameter errors: This account's password is incorrect",
            10505 => "Parameter errors: Disabled account",
            10507 => "Parameter errors: This account is non-agent referral; this function is not available",
            10512 => "Parameter errors: Account password format is incorrect",
            10601 => "Parameter errors: Limits are not open, please check"
        ],
        "GetMemberTradeReport" => [
            0 => "Success",
            107 => "Operation successfully: The operation is successful but no data was found",
            10501 => "Operation failure: No such account, please check it",
            10502 => "Parameter errors: The account name must not be blank"
        ],
        "SigninGame" => [
            0 => "Success",
            103 => "Parameter errors: Agent ID and identifier format error",
            900 => "Parameter errors: No such function",
            911 => "Operation failure: Server is busy",
            10201 => "Parameter errors: This function allows to inquire the report within one day; you have exceeded the maximum number",
            10202 => "Parameter errors: Timestamp is abnormal, more than 30 seconds.",
            10301 => "Parameter errors: Agent ID is null, please check (vendorId)",
            10302 => "Parameter errors: No Agent ID Record",
            10303 => "Parameter errors: Agent exists but agent code (signature) is wrong",
            10304 => "Parameter errors: agent code (signature) is null",
            10411 => "Operation failure: Please try again in 30 seconds.",
            10418 => "Operation failure: Please try again in 10 seconds.",
            10501 => "Parameter errors: No such account, please check it",
            10502 => "Parameter errors: The account name must not be blank",
            10504 => "Parameter errors: This account's password is incorrect",
            10505 => "Parameter errors: Disabled account",
            10507 => "Parameter errors: This account is non-agent referral; this function is not available",
            10512 => "Parameter errors: Account password format is incorrect",
            10601 => "Parameter errors: Limits are not open, please check"
        ],
        "GetDateTimeReport" => [
            0 => "Success",
            107 => "Operation successfully: The operation is successful but no data was found",
            101 => "Operation failure: The ID information is wrong",
            102 => "Parameter errors: Time format is wrong",
        ],
    ];

    protected $cmd;
    protected $vendorId;
    protected $signature;
    protected $user;
    protected $password;
    protected $username;
    protected $timestamp;
    protected $syslang;
    protected $money;
    protected $order;
    protected $lang;
    protected $startTime;
    protected $endTime;
    protected $datatype;
    protected $timetype;


    public static function init($function, $params)
    {
        $controller = new _WMCasinoController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function get_url()
    {
        return config('api.WM_LINK');
    }

    public function make_params($function)
    {
        switch ($function) {
            case "MemberRegister":
                return [
                    'cmd' => $this->cmd,
                    'vendorId' => $this->vendorId,
                    'signature' => $this->signature,
                    'user' =>  $this->user,
                    'password' => $this->password,
                    'username' =>  $this->username,
                    'timestamp' => $this->timestamp,
                    'syslang' => $this->syslang,
                ];
            case "GetBalance";
                return [
                    'cmd' => $this->cmd,
                    'vendorId' => $this->vendorId,
                    'signature' => $this->signature,
                    'user' =>  $this->user,
                    'timestamp' => $this->timestamp,
                    'syslang' => $this->syslang,
                ];
            case "GetAgentBalance";
                return [
                    'cmd' => $this->cmd,
                    'vendorId' => $this->vendorId,
                    'signature' => $this->signature,
                    'timestamp' => $this->timestamp,
                ];
            case "ChangeBalance":
                return [
                    'cmd' => $this->cmd,
                    'vendorId' => $this->vendorId,
                    'signature' => $this->signature,
                    'user' =>  $this->user,
                    'money' => $this->money,
                    'order' => $this->order,
                    'timestamp' => $this->timestamp,
                    'syslang' => $this->syslang,
                ];
            case "GetMemberTradeReport":
                return [
                    'cmd' => $this->cmd,
                    'vendorId' => $this->vendorId,
                    'signature' => $this->signature,
                    'user' =>  $this->user,
                    'order' => $this->order,
                    'timestamp' => $this->timestamp,
                    'syslang' => $this->syslang,
                ];
            case "SigninGame":
                return [
                    'cmd' => $this->cmd,
                    'vendorId' => $this->vendorId,
                    'signature' => $this->signature,
                    'user' =>  $this->user,
                    'password' => $this->password,
                    'lang' => $this->lang,
                    'timestamp' => $this->timestamp,
                    'syslang' => $this->syslang,
                ];
            case "LogoutGame":
                return [
                    'cmd' => $this->cmd,
                    'vendorId' => $this->vendorId,
                    'signature' => $this->signature,
                    'user' =>  $this->user,
                    'timestamp' => $this->timestamp,
                    'syslang' => $this->syslang,
                ];
            case "GetDateTimeReport":
                return [
                    'cmd' => $this->cmd,
                    'vendorId' => $this->vendorId,
                    'signature' => $this->signature,
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                    'datatype' => $this->datatype,
                    'timetype' => $this->timetype,
                    'timestamp' => $this->timestamp,
                    'syslang' => $this->syslang,
                ];

        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_WM,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
        

        $log = 'wm_api_records';
        if ($function == "GetDateTimeReport") {
            $log = 'wm_api_ticket_records';
        }
        if ($function == "ChangeBalance" || $function == "GetMemberTradeReport") {
            $log = 'wm_api_transfer_records';
        }
        if ($function == "GetBalance") {
            $log = 'wm_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url(), [
                'headers' => [
                    'Content-Type' => 'application/json' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'query' => $this->make_params($function),
            ]);

            $response = @json_decode($response->getBody(), true);
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

        if (isset($response['errorMessage'])) {
            $message = $response['errorMessage'];
        } elseif (isset(SELF::ERRORS[$function][$response['errorCode']])) {
            $message = SELF::ERRORS[$function][$response['errorCode']];
        } else {
            $message = "Unknown Error";
        }

        $logForDB['message'] = $message;
        
        Log::channel($log)->debug("$time Status Message: $message");

        if($response['errorCode'] != 0 && $response['errorCode'] != 107){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }
      
        
        return [
            'status' => ($response['errorCode'] == 0 || $response['errorCode'] == 107) ? true : false,
            'status_message' => $message,
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return 1;
        }
        if (app()->getLocale() == "cn") {
            return 0;
        }
        if (request()->lang == "bm") {
            return 7;
        }
        return 1;
    }

}