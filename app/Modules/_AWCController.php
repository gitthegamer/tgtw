<?php

namespace App\Modules;

use Exception;
use App\Models\Log as ModelsLog;

class _AWCController
{
    const ERRORS = [
        "0000" => "Success",
    ];

    protected $platform;
    protected $gameCode;
    protected $gameType;
    protected $timeFrom;
    protected $cert;
    protected $agentId;
    protected $currency;
    protected $startTime;
    protected $endTime;


    public static function init($function, $params)
    {
        $controller = new _AWCController();
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
        return config('api.AWC_REPORT_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "fetch/gzip/getTransactionByUpdateDate":
              
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    "timeFrom" => $this->timeFrom,
                    'platform' => $this->platform,
                    'currency' => $this->currency,
                    'gameType' => $this->gameType
                ];
            case "fetch/gzip/getTransactionByTxTime":
              
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                    'platform' => $this->platform,
                    'currency' => $this->currency,
                ];
            case "fetch/getSummaryByTxTimeHour":
              
                return [
                    'cert' => $this->cert,
                    'agentId' => $this->agentId,
                    'startTime' => $this->startTime,
                    'endTime' => $this->endTime,
                    'platform' => $this->platform,
                    'currency' => $this->currency,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "GET";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_AWC,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $this->create_param($function, $params);        
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json' // Replace with the appropriate media type
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time) {
             
             
                },
                'query' => $this->make_params($function),
            ]);

            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            // ModelsLog::addLog($logForDB);
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

        if(($response['status'] !== "0000") && ($response['status'] !== "1001")){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }
        
        return [
            'status' => (($response['status'] == "0000") || ($response['status'] == "1001")),
            'status_message' => "Unknown ERROR",
            'data' => $response
        ];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "zh";
        }
        return "en";
    }
}
