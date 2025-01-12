<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _28WINController
{

    protected $apiUser;
    protected $apiPass;
    protected $user;
    protected $pass;
    protected $loginID;
    protected $loginPass;
    protected $fullName;
    protected $amount;
    protected $params;
    protected $sessionID;
    protected $tokenCode;
    protected $dateFrom;
    protected $dateTo;
    protected $newPass;
    protected $status;
    protected $page;
    protected $drawDate;
    protected $currency;
    protected $newpass;
    protected $drawType;

    const ERROR_ARRAYS = [
        "0" => "Successfully",
        "1" => "Invalid API User/Password",
        "2" => "Invalid Login ID/Password",
        "3" => "Missing Parameters / Data Not Found",
        "4" => "Login ID Existed / Invalid New Status",
        "5" => "Invalid Amount",
        "21" => "Missing/Invalid Draw Date",
        "22" => "Login ID Not Existed",
        "99" => "System Maintenance",
        "998" => "System Busy",
        "999" => "ex.Message",
    ];

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function make_params($function)
    {
        if ($function == "getProfile.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
            ];
        }
        if ($function == "createPlayer.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
                'loginID' => $this->loginID,
                'loginPass' => $this->loginPass,
                'fullName' => $this->fullName,
            ];
        }
        if ($function == "changePasswordStatus.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
                'loginID' => $this->loginID,
                'newpass' => $this->newpass,
            ];
        }
        if ($function == "deposit.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
                'loginID' => $this->loginID,
                'amount' => $this->amount,
            ];
        }
        if ($function == "withdraw.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
                'loginID' => $this->loginID,
                'amount' => $this->amount,
            ];
        }
        if ($function == "betLogin.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
            ];
        }
        if ($function == "betlistPage.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
                'dateFrom' => $this->dateFrom,
                'dateTo' => $this->dateTo,
                'page' => $this->page,
            ];
        }
        if ($function == "winLoss.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
                'dateFrom' => $this->dateFrom,
                'dateTo' => $this->dateTo,
                'drawType' => $this->drawType,
                'currency' => 'SG2',
            ];
        }
        if ($function == "winNumber.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
                'dateFrom' => $this->dateFrom,
                'dateTo' => $this->dateTo,
            ];
        }
        if ($function == "txnList.aspx") {
            return [
                'apiUser' => $this->apiUser,
                'apiPass' => $this->apiPass,
                'user' => $this->user,
                'pass' => $this->pass,
                'loginID' => $this->loginID,
            ];
        }
    }

    public function get_url($function)
    {
        return config('api.WIN28_LINK') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _28WINController();
        return $controller->request($function, $params);
    }

    public static function generateLogin($user, $sessionID, $tokenCode, $isMobileLogin)
    {
        if ($isMobileLogin) {
            return config('api.WIN28_APP_LOBBY') . "?user=$user&sessionID=$sessionID&tokenCode=$tokenCode";
        } else {
            return config('api.WIN28_APP_LOBBY') . "?user=$user&sessionID=$sessionID&tokenCode=$tokenCode";
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "GET";

        $logForDB = [
            'channel' => ModelsLog::CHANNEL_28Win,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       


        $log = '28win_api_records';
        if ($function == "winLoss.aspx" || $function == "betlistPage.aspx" || $function == "winNumber.aspx") {
            $log = '28win_api_ticket_records';
        }
        if ($function == "deposit.aspx" || $function == "withdraw.aspx" || $function == "txnList.aspx") {
            $log = '28win_api_transfer_records';
        }
        if ($function == "getProfile.aspx") {
            $log = '28win_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);
        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'query' => $this->make_params($function),
            ]);
            Log::channel($log)->debug("$time Response: " . (string)$response->getBody()->getContents());
            $response = @json_decode(json_encode(simplexml_load_string($response->getBody())), true);

            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);

            Log::channel($log)->debug("$time Response: " . json_encode($response));
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . (SELF::ERROR_ARRAYS['errorCode'] ?? "UNKNOWN ERROR") . "$e");

            return [
                'status' => false,
                'status_message' => SELF::ERROR_ARRAYS['errorCode'] ?? "UNKNOWN ERROR",
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
                'status_message' => SELF::ERROR_ARRAYS['errorCode'] ?? "Unknown ERROR",
                'data' => null,
            ];
        }

        if($response['errorCode'] != "0" && $response['errorCode'] != "4" && $response['errorCode'] != "3"){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }        

        return [
            'status' => ($response['errorCode'] == "0" || $response['errorCode'] == "4") ? true : false,
            'status_message' => SELF::ERROR_ARRAYS[$response['errorCode']] ?? "Unknown Error",
            'data' => $response
        ];
    }
}
