<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class _NextSpinController
{

    const ERRORS = [
        "0" => "Success", // Interface call is successful.
        "1" => "System Error", // Internal system error at Game provider. Example: Program bug or database connection failed.
        "2" => "Invalid Request", // Request is not supported by Game provider.
        "3" => "Service Inaccessible", // Game provider API down.
        "100" => "Request Timeout", // Request timeout.
        "101" => "Call Limited", // User call frequency exceeds the limit.
        "104" => "Request Forbidden", // Request is forbidden.
        "105" => "Missing Parameters", // Missing necessary parameters.
        "106" => "Invalid Parameters", // Invalid parameters.
        "107" => "Duplicated Serial No", // Duplicated batch number.
        "108" => "Merchant Key Error", // Apk version and PC version merchant login key error.
        "110" => "Record Id Not Found", // Batch number does not exist.
        "10113" => "Merchant Not Found", // Merchant does not exist.
        "112" => "Api Call Limited", // Exceeded the limit of calling API.
        "113" => "Invalid Acct Id", // Acct ID incorrect format.
        "50100" => "Acct Not Found", // User does not exist.
        "50101" => "Acct Inactive", // Account not activated.
        "50102" => "Acct Locked", // Account locked.
        "50103" => "Acct Suspend", // Account suspended.
        "50104" => "Token Validation Failed", // Token validation failed.
        "50110" => "Insufficient Balance", // Account balance is insufficient.
        "50111" => "Exceed Max Amount", // Exceeded account transaction limit.
        "50112" => "Currency Invalid", // Deposit/withdraw currency code are not consistent with Player’s default currency code or merchant does not use the currency code.
        "50113" => "Amount Invalid", // Deposit/withdraw amount must be greater than > 0.
        "10104" => "Password Invalid", // Password does not match.
        "30003" => "Bet Setting Incomplete", // Bet setting incomplete.
        "10103" => "Acct Not Found", // Member does not exist.
        "10105" => "Acct Status Inactived", // Account not activated.
        "10110" => "Acct Locked", // Account locked.
        "10111" => "Acct Suspend", // Account suspended.
        "11101" => "Bet Insufficient Balance", // Balance insufficient.
        "11102" => "Bet Draw Stop Bet", // Bet draw stop bet.
        "11103" => "Bet Type Not Open", // Bet type not open.
        "11104" => "Bet Info Incomplete", // Betting information incomplete.
        "11105" => "Bet Acct Info Incomplete", // Account information abnormal.
        "11108" => "Bet Request Invalid", // Invalid betting request.
        "12001" => "Bet Setting Incomplete", // Betting setting incomplete.
        "1110801" => "Bet Request Invalid Max", // Bet maximum exceeds the upper limit.
        "1110802" => "Bet Request Invalid Min", // Bet minimum exceeds the lower limit.
        "1110803" => "Bet Request Invalid Totalbet", // Bet amount error.
        "50200" => "Game Currency Not Active" // Game in the specified currency not activated.
    ];

    protected $acctId;
    protected $token;
    protected $channel;
    protected $currency;
    protected $isLobby;
    protected $language;
    protected $userName;
    protected $amount;
    protected $isMobileLogin;
    protected $platform;
    protected $gameCode;
    protected $gameType;
    protected $serialNo;
    protected $merchantCode;
    protected $pageIndex;
    protected $beginDate;
    protected $endDate;

    public static function init($function, $params)
    {
        $controller = new _NextSpinController();
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
        return config('api.NEXTSPIN_LINK');
    }

    public function make_params($function)
    {
        switch ($function) {
            case "getAcctInfo":
                return [
                    'acctId' => $this->acctId,
                    'merchantCode' => $this->merchantCode,
                    "serialNo" => $this->serialNo,
                ];
            case "deposit":
                return [
                    'acctId' => $this->acctId,
                    'amount' => $this->amount,
                    'currency' => $this->currency,
                    'merchantCode' => $this->merchantCode,
                    'serialNo' => $this->serialNo,
                ];
            case "withdraw":
                return [
                    'acctId' => $this->acctId,
                    'currency' => $this->currency,
                    'amount' => $this->amount,
                    'merchantCode' => $this->merchantCode,
                    'serialNo' => $this->serialNo
                ];
            case "getBetHistory":
                return [
                    'beginDate' => $this->beginDate,
                    'endDate' => $this->endDate,
                    'pageIndex' => $this->pageIndex,
                    'merchantCode' => $this->merchantCode,
                    'serialNo' => $this->serialNo

                ];
            case 'checkTransfer':
                return [
                    'merchantCode' => $this->merchantCode,
                    'serialNo' => $this->serialNo
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_NEXTSPIN,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       

        $log = 'nextspin_api_records';
        if ($function == "getBetHistory") {
            $log = 'nextspin_api_ticket_records';
        }
        if ($function == "checkTransfer") {
            $log = 'nextspin_api_transfer_records';
        }
        if ($function == "getAcctInfo") {
            $log = 'nextspin_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url(), [
                'headers' => [
                    'Content-Type' => 'application/json', // Replace with the appropriate media type
                    'DataType' => 'JSON',
                    'API' => $function,
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'json' => $this->make_params($function),
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

        if($response['code'] != 0){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => ($response['code'] == 0) ? true : false,
            'status_message' => $response['msg'] ?? self::ERRORS[$response['code']] ?? "Unknown Error",
            'data' => $response
        ];
    }

    public static function callback()
    {
        Log::channel('nextspin_api_login_records')->debug("callback start");
        $request = json_decode(str_replace("json=", "", request()->getContent()));
        Log::channel('nextspin_api_login_records')->debug("request : " . request()->getContent());
        $result = false;
        try {
            $member = \App\Models\MemberAccount::where('username', $request->acctId)->first();
            log::debug("member : " . $member->balance());
            if ($member) {
                $result = [
                    "acctInfo" => [
                        "acctId" => $member->username,
                        "balance" => $member->balance(),
                        "currency" => "MYR",
                    ],
                    "merchantCode" => config('api.NEXTSPIN_MERCHANTCODE'),
                    "code" => "0",
                    "serialNo" => \Illuminate\Support\Str::uuid(),
                    "msg" => "success",
                ];
            } else {
                $result = [
                    "acctInfo" => "",
                    "merchantCode" => config('api.NEXTSPIN_MERCHANTCODE'),
                    "code" => "1",
                    "serialNo" => \Illuminate\Support\Str::uuid(),
                    "msg" => "Login Failed",
                ];
            }
        } catch (Throwable $e) {
            Log::channel('nextspin_api_login_records')->debug("exception : " . $e);
        }

        if ($result !== false) {
            $response = $result;
        } else {
            $response = [
                "acctInfo" => "",
                "merchantCode" => config('api.NEXTSPIN_MERCHANTCODE'),
                "code" => "2",
                "serialNo" => \Illuminate\Support\Str::uuid(),
                "msg" => "Unknown Error",
            ];
        }
        Log::channel('nextspin_api_login_records')->debug("response : " . json_encode($response));
        Log::channel('nextspin_api_login_records')->debug("callback end");

        header('Content-Type: application/json');

        return json_encode($response);
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "en_US";
        }
        if (app()->getLocale() == "cn") {
            return "zh_CN";
        }
        if (request()->lang == "bm") {
            return "id_ID";
        }
        return "en_US";
    }
}
