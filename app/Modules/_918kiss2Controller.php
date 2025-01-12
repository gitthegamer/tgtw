<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _918kiss2Controller
{
    protected $apiuserid;
    protected $apipassword;
    protected $operation;
    protected $playername;
    protected $playertelno;
    protected $playerdescription;
    protected $playerpassword;
    protected $playerid;
    protected $amount;
    protected $tid;
    protected $startdate;
    protected $enddate;
    protected $date;
    protected $starttime;
    protected $endtime;


    public static function init($function, $params)
    {
        $controller = new _918kiss2Controller();
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
        return config('api.KISS2_API_URL') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "player":
                return [
                    "apiuserid" => $this->apiuserid,
                    "apipassword" => $this->apipassword,
                    "operation" => $this->operation,
                    "playername" => $this->playername,
                    "playertelno" => $this->playertelno,
                    "playerdescription" => $this->playerdescription,
                    "playerpassword" => $this->playerpassword,
                    "playerid" => $this->playerid,
                ];
            case "funds":
                return [
                    "apiuserid" => $this->apiuserid,
                    "apipassword" => $this->apipassword,
                    "operation" => $this->operation,
                    "playerid" => $this->playerid,
                    "amount" => $this->amount,
                    "tid" => $this->tid,
                ];
            case "reports":
                switch ($this->operation) {
                    case "gamelog":
                        return [
                            "apiuserid" => $this->apiuserid,
                            "apipassword" => $this->apipassword,
                            "operation" => $this->operation,
                            "playerid" => $this->playerid,
                            "starttime" => $this->starttime,
                            "endtime" => $this->endtime,
                            "date" => $this->date,
                        ];
                    case "totalreport":
                        return [
                            "apiuserid" => $this->apiuserid,
                            "apipassword" => $this->apipassword,
                            "operation" => $this->operation,
                            "startdate" => $this->startdate,
                            "enddate" => $this->enddate,
                        ];
                }
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_918Kiss2,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $log = '918kiss2_api_records';
        if ($function == "reports") {
            $log = '918kiss2_api_ticket_records';
        }
        if ($function == "funds") {
            $log = '918kiss2_api_transfer_records';
        }
        if ($function == "player" && $params['operation'] == "getplayerinfo") {
            $log = '918kiss2_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);
        Log::channel($log)->debug("$time Params : " . json_encode($params));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
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
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            Log::channel($log)->error("Error: " . $e->getMessage());
            Log::channel($log)->error("Stack Trace: " . $e->getTraceAsString());
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

        if ($response['returncode'] != 0) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => $response['returncode'] == 0 ? true : false,
            'status_message' => $response['message'],
            'data' => $response
        ];
    }

    public static function generateSign($authcode, $userName, $time, $secretkey)
    {
        return strtoupper(md5(strtolower($authcode . $userName . $time . $secretkey)));
    }

    public static function generate_username()
    {
        return random_int(100000, 9999999);
    }

    public static function generateTime()
    {
        return (int) (microtime(true) * 1000);
    }

    public static function generateIP()
    {
        return "141.164.55.98";
    }
}
