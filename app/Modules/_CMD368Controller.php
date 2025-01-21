<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class _CMD368Controller
{

    const ERRORS = [
        "0" => "Success",
        "1" => "Failed",
        "95" => "Illegal Request",
        "96" => "Transaction ID Already Exists",
        "97" => "User Not Exists",
        "98" => "User Already Exists",
        "100" => "Invalid Arguments",
        "101" => "Under Maintenance",
        "102" => "Request Limit",
        "103" => "Access Denied",
        "999" => "Server Exception",
        "1000" => "Server Timeout"
    ];

    protected $PartnerKey;
    protected $UserName;
    protected $Currency;
    protected $PaymentType;
    protected $Money;
    protected $TicketNo;
    protected $Version;
    protected $TimeType;
    protected $StartDate;
    protected $EndDate;
    protected $Method;

    public static function init($function, $params)
    {
        $controller = new _CMD368Controller();
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
        return config('api.CMD368_API_LINK');
    }

    public function make_params($function)
    {
        switch ($function) {
            case "createmember":
                return [
                    'Method' => $this->Method,
                    'PartnerKey' => $this->PartnerKey,
                    'UserName' => $this->UserName,
                    'Currency' => $this->Currency,
                ];
            case "getbalance":
                return [
                    'Method' => $this->Method,
                    'PartnerKey' => $this->PartnerKey,
                    'UserName' => $this->UserName,
                ];
            case "balancetransfer":
                return [
                    'Method' => $this->Method,
                    'PartnerKey' => $this->PartnerKey,
                    'UserName' => $this->UserName,
                    'PaymentType' => $this->PaymentType,
                    'Money' => $this->Money,
                    'TicketNo' => $this->TicketNo, //unique
                ];
            case "betrecord":
                return [
                    'Method' => $this->Method,
                    'PartnerKey' => $this->PartnerKey,
                    'Version' => $this->Version,
                    'TimeType' => $this->TimeType,
                    'StartDate' => $this->StartDate,
                    'EndDate' => $this->EndDate,
                ];
            case 'checkfundtransferstatus':
                return [
                    'Method' => $this->Method,
                    'PartnerKey' => $this->PartnerKey,
                    'UserName' => $this->UserName,
                    'TicketNo' => $this->TicketNo, //unique
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "GET";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_CMD368,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
      

        $log = 'cmd368_api_records';
        if ($function == "betrecord") {
            $log = 'cmd368_api_ticket_records';
        }
        if ($function == "checkfundtransferstatus") {
            $log = 'cmd368_api_transfer_records';
        }
        if ($function == "getbalance") {
            $log = 'cmd368_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->get_url(), [
                // 'headers' => [
                    // 'Content-Type' => 'application/xml', // Replace with the appropriate media type
                    // 'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                // ],
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

        if($response['Code'] != 0){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => ($response['Code'] == 0) ? true : false,
            'status_code' => $response['Code'],
            'status_message' => $response['Message'] ?? self::ERRORS[$response['Code']] ?? "Unknown Error",
            'data' => $response['Data'],
        ];
    }

    public static function callback()
    {
        Log::debug(json_encode(request()->all()));
        Log::channel('cmd368_api_login_records')->debug("callback start");
        $token = request()->query('token');
        $secretKey = request()->query('secret_key');
        Log::channel('cmd368_api_login_records')->debug("token: " . $token);
        Log::channel('cmd368_api_login_records')->debug("secret_key: " . $secretKey);

        if (!$token || !$secretKey) {
            Log::channel('cmd368_api_login_records')->error("Missing token or secret key");
            return response()->json(['error' => 'Missing token or secret key'], 400);
        }

        try {
            $requestContent = request()->getContent();
            if (empty($requestContent)) {
                throw new \Exception("No request content received");
            }
            $request = json_decode(json_encode(simplexml_load_string($requestContent)), true);
            if (!$request) {
                throw new \Exception("Failed to parse request content");
            }
            Log::channel('cmd368_api_login_records')->debug("request : " . $requestContent);

            $member = \App\Models\Member::where('token', str_replace('CMD368', '', $token))->first();
            if ($member) {
                $result = [
                    "authenticate" => [
                        "member_id" => $member->code,
                        "status_code" => "0",
                        "message" => "Success"
                    ]
                ];
            } else {
                $result = [
                    "authenticate" => [
                        "member_id" => "",
                        "status_code" => "2",
                        "message" => "Failed"
                    ]
                ];
            }
        } catch (\Throwable $e) {
            Log::channel('cmd368_api_login_records')->error("exception : " . $e->getMessage());
            $result = [
                "authenticate" => [
                    "member_id" => "",
                    "status_code" => "999",
                    "message" => "Unknown Error: " . $e->getMessage()
                ]
            ];
        }

        Log::channel('cmd368_api_login_records')->debug("response : " . json_encode($result));
        Log::channel('cmd368_api_login_records')->debug("callback end");

        $xml = new \SimpleXMLElement('<authenticate/>');
        array_walk_recursive($result['authenticate'], function($value, $key) use ($xml) {
            $xml->addChild($key, $value);
        });

        return response($xml->asXML(), 200)->header('Content-Type', 'application/xml');
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "en-US";
        }
        if (app()->getLocale() == "cn") {
            return "zh-CN";
        }
        if (request()->lang == "bm") {
            return "id-ID";
        }
        return "en-US";
    }
}
