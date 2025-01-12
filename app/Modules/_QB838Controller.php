<?php

namespace App\Modules;

use App\Http\Helpers;
use App\Http\Middleware\Login;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _QB838Controller
{
    const ERRORS = [
        "000000" =>  "Success",
        "000001" =>  "System maintenance",
        "000002" =>  "Has no permission",
        "000003" =>  "IP is not in white list",
        "000004" =>  "APIPassword wrong",
        "000005" =>  "System busy",
        "000006" =>  "Time period exceed limitation",
        "000007" =>  "Request parameter wrong",
        "000008" =>  "Request too frequent",
        "010001" =>  "Account not exists",
        "100001" =>  "Invalid currency",
        "100002" =>  "Account name illegal",
        "100003" =>  "Account already exists",
        "100004" =>  "Create account failed",
        "100005" =>  "Wrong currency",
        "200001" =>  "Login fail",
        "200002" =>  "Account Closed",
        "200003" =>  "Account Paused",
        "300001" =>  "Transfer amount should be larger than 0",
        "300002" =>  "Transfer failed",
        "300003" =>  "Duplicate transfer serial number",
        "300004" =>  "Insufficient balance",
        "300005" =>  "Transfer verify key wrong",
        "400001" =>  "Transfer SN not exists",
        "400002" =>  "Transfer SN not match the account",
        "500001" =>  "Update credit limitation failed",
        "500002" =>  "Commission level wrong",
        "500003" =>  "OddsStyle wrong",
        "600001" =>  "Credit limitation not exist",
        "800001" =>  "Update member status failed",
    ];

    protected $CompanyKey;
    protected $APIPassword;
    protected $MemberAccount;
    protected $Currency;
    protected $SerialNumber;
    protected $Amount;
    protected $TransferType;
    protected $Key;
    protected $LoginIP;
    protected $Language;
    protected $AgentID;
    protected $SortNo;
    protected $Rows;
    protected $Items;

    public static function init($function, $params)
    {
        $controller = new _QB838Controller();
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
        return config('api.QB838_LINK') . $function;
    }

    public function make_params($function)
    {
        if ($function == "SportApi/Register") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                'MemberAccount' => $this->MemberAccount,
                'Currency' =>  $this->Currency,

            ];
        }
        if ($function == "SportApi/GetMemberStatus") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                'MemberAccount' => $this->MemberAccount,
            ];
        }
        if ($function == "SportApi/GetBalance") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                'MemberAccount' => $this->MemberAccount,
            ];
        }
        if ($function == "SportApi/Transfer") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                'MemberAccount' => $this->MemberAccount,
                'SerialNumber' => $this->SerialNumber,
                'Amount' => $this->Amount,
                'TransferType' => $this->TransferType,
                'Key' => $this->Key,
            ];
        }
        if ($function == "SportApi/Logout") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                'MemberAccount' => $this->MemberAccount,
            ];
        }
        if ($function == "SportApi/CheckTransfer") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                'SerialNumber' => $this->SerialNumber,
            ];
        }
        if ($function == "SportApi/Login") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                'MemberAccount' => $this->MemberAccount,
                'LoginIP' => $this->LoginIP,
                'Language' => $this->Language,
            ];
        }
        if ($function == "SportApi/GetBetSheetBySort") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                // 'AgentID' => $this->AgentID,
                'SortNo' => $this->SortNo,
                'Rows' => $this->Rows,
            ];
        }

        if ($function == "SportApi/SetGameParamLimit") {
            return [
                'CompanyKey' => $this->CompanyKey,
                'APIPassword' => $this->APIPassword,
                'MemberAccount' => $this->MemberAccount,
                'Items' => $this->Items,
            ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_QB838,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $log = 'qb838_api_records';
        if ($function == "SportApi/GetBetSheetBySort") {
            $log = 'qb838_api_ticket_records';
        }
        if ($function == "SportApi/CheckTransfer" || $function == "SportApi/Transfer") {
            $log = 'qb838_api_transfer_records';
        }
        if ($function == "SportApi/GetBalance") {
            $log = 'qb838_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);

        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json', 
                    'CompanyKey' => config('api.QB838_KEY'),
                    'APIPassword' => config('api.QB838_APIPASS'),
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'body' => json_encode($this->make_params($function)),
            ]);

            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            Log::channel($log)->debug("$time Response: " . json_encode($response));
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time Unknown ERROR : " . "$e");

            return [
                'status' => false,
                'status_code' => null,
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

        if (isset(SELF::ERRORS[$response['ErrorCode']])) {
            $message =  SELF::ERRORS[$response['ErrorCode']];
        } else {
            $message = "Unknown Error";
        }
        $logForDB['message'] = $message;

        Log::channel($log)->debug("$time Status: $message");

        if ($response['ErrorCode'] !== "000000") {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => $response['ErrorCode'] == "000000" ? true : false,
            'status_code' => $response['ErrorCode'],
            'status_message' => $response['ErrorCode'] ?? self::ERRORS[$response['ErrorCode']] ?? "Unknown Error",
            'data' => isset($response['Data']) ? $response['Data'] : null,
        ];
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "en";
        }
        if (app()->getLocale() == "cn") {
            return "ch";
        }
        if (request()->lang == "bm") {
            return "in";
        }
        return "en";
    }

    public static function getGameParamLimiteList()
    {
        $params = [
            [
                "SportID" => 0,
                "NormalLimit" => [
                    "Maxbet" => 1,
                    "Minbet" => 1,
                    "PerMaxbet" => 1,
                    "PerMaxpay" => 1,
                ],
                "MixParlayLimit" => [
                    "Maxbet" => 1,
                    "Minbet" => 1,
                    "PerMaxbet" => 1,
                    "PerMaxpay" => 1,
                ],
                "MarketLimit" => [
                    "9" => [
                        "Maxbet" => 1,
                        "Minbet" => 1,
                        "PerMaxbet" => 1,
                        "PerMaxpay" => 1,
                    ],
                    "10" => [
                        "Maxbet" => 1,
                        "Minbet" => 1,
                        "PerMaxbet" => 1,
                        "PerMaxpay" => 1,
                    ],
                ],
                "PerMaxpay" => 1,
                "PerMaxbet" => 1,
            ],
            [
                "SportID" => 1,
                "NormalLimit" => [
                    "Maxbet" => 3000,
                    "Minbet" => 5,
                    "PerMaxbet" => 3000,
                    "PerMaxpay" => 3000,
                ],
                "MixParlayLimit" => [
                    "Maxbet" => 3000,
                    "Minbet" => 5,
                    "PerMaxbet" => 3000,
                    "PerMaxpay" => 3000,
                ],
                "MarketLimit" => [
                    "9" => [
                        "Maxbet" => 3000,
                        "Minbet" => 5,
                        "PerMaxbet" => 3000,
                        "PerMaxpay" => 3000,
                    ],
                    "10" => [
                        "Maxbet" => 3000,
                        "Minbet" => 5,
                        "PerMaxbet" => 3000,
                        "PerMaxpay" => 3000,
                    ],
                ],
                "PerMaxpay" => 3000,
                "PerMaxbet" => 3000,
            ]
        ];




        // $resultArray = [];
        // for ($i = 3; $i <= 26; $i++) {
        //     $tempArray = $baseArray;
        //     $tempArray['SportID'] = $i;
        //     $resultArray[] = $tempArray;
        // }

        // array_unshift($resultArray, $baseArray);

        return $params;
    }
}
