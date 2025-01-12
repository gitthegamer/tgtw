<?php

namespace App\Modules;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use App\Models\Log as ModelsLog;
use GuzzleHttp\Client;

class _MegaH5Controller
{

    // protected $agentLoginId;
    // protected $secretCode;
    // protected $sn;
    // protected $loginId;
    // protected $amount;
    // protected $bizId;
    // protected $startTime;
    // protected $endTime;
    // protected $pageIndex;
    protected $OperatorId;
    protected $RequestDateTime;
    protected $PlayerId;
    protected $Signature;
    protected $Amount;
    protected $ReferenceId;
    protected $GameCode;
    protected $Currency;
    protected $Key;
    protected $Page;
    protected $TransactionIds;
    protected $Ip;
    protected $TranId;

    public static function init($function, $params)
    {
        $controller = new _MegaH5Controller();
        return $controller->request($function, $params);
    }

    public function get_url($function)
    {
        return config('api.MEGAH5_LINK') . $function;
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->Signature = $this->encrytData($function);
        $params['Signature'] = $this->Signature;
        $this->Key = $params;
    }

    public function make_params($function)
    {
        if ($function == "CreatePlayer" || $function == "CheckBalance") {
            return [
                'OperatorId' => $this->OperatorId,
                'RequestDateTime' => $this->RequestDateTime,
                'Signature' => $this->Signature,
                'PlayerId' => $this->PlayerId,
            ];
        }

        if ($function == "Deposit" || $function == "Withdraw") {
            return [
                'OperatorId' => $this->OperatorId,
                'RequestDateTime' => $this->RequestDateTime,
                'Signature' => $this->Signature,
                'PlayerId' => $this->PlayerId,
                'Amount' => $this->Amount,
                'ReferenceId' => $this->ReferenceId,
            ];
        }

        if ($function == "GameLogin") {
            return [
                'OperatorId' => $this->OperatorId,
                'RequestDateTime' => $this->RequestDateTime,
                'Signature' => $this->Signature,
                'PlayerId' => $this->PlayerId,
                'Ip' => $this->Ip,
                'GameCode' => $this->GameCode,
                'Currency' => $this->Currency,
            ];
        }

        if ($function == "GetTransactionDetails") {
            return [
                'OperatorId' => $this->OperatorId,
                'RequestDateTime' => $this->RequestDateTime,
                'Signature' => $this->Signature,
                'TranId' => $this->TranId,
            ];
        }

        if ($function == "PullLog") {
            return [
                'OperatorId' => $this->OperatorId,
                'RequestDateTime' => $this->RequestDateTime,
                'Signature' => $this->Signature,
            ];
        }

        if ($function == "FlagLog") {
            return [
                'OperatorId' => $this->OperatorId,
                'RequestDateTime' => $this->RequestDateTime,
                'Signature' => $this->Signature,
                'TransactionIds' => $this->TransactionIds,
            ];
        }

        Log::error("Unsupported function: $function");
        return null;
    }

    // public function request($function, $params)
    // {
    //     $time = time();
    //     $method = "POST";
    //     $log_id = ModelsLog::addLog([
    //         'channel' => ModelsLog::CHANNEL_MegaH5,
    //         'function' => $function,
    //         'params' => json_encode($params),
    //         'method' => $method,
    //     ]);
    //     $logForDB = ['id' => $log_id];

    //     $log = 'MegaH5_api_records';
    //     if ($function == "PullLog" || $function == "FlagLog") {
    //         $log = 'MegaH5_api_ticket_records';
    //     }
    //     if ($function == "Deposit" || $function == "Withdraw") {
    //         $log = 'MegaH5_api_transfer_records';
    //     }
    //     if ($function == "CheckBalance") {
    //         $log = 'MegaH5_api_balance_records';
    //     }

    //     Log::channel($log)->debug("$time Function: " . $function);
    //     $this->create_param($function, $params);
    //     $params = $this->make_params($function);
    //     $url = $this->get_url($function);
    //     Log::channel($log)->debug("$time URL: " . $url);

    //     try {
    //         $client = new Client();

    //         $options = [
    //             'headers' => [
    //                 'Content-Type' => 'application/json',
    //             ],
    //             'json' => $params, // Ensure the correct params are sent
    //             'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
    //                 Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
    //             },
    //         ];

    //         $response = $client->request('POST', $url, $options);
    //         $responseBody = $response->getBody();
    //         $responseArray = @json_decode($responseBody, true);

    //         if (!$responseArray) {
    //             $logForDB['status'] = ModelsLog::STATUS_ERROR;
    //             $logForDB['trace'] = "$time Status: Unknown";
    //             ModelsLog::addLog($logForDB);
    //             Log::channel($log)->debug("$time Status: Unknown - Response: " . $responseBody);
    //             return [
    //                 'status' => false,
    //                 'status_message' => "Connection Error",
    //                 'data' => [],
    //             ];
    //         }

    //         if (isset($responseArray['Status'])) {
    //             $status = $responseArray['Status'];
    //             $message = $status == 200 ? "OK" : ($responseArray['Description'] ?? "Error");
    //             Log::channel($log)->debug("$time Status: " . $message);

    //             $logForDB['status'] = $status == 200 ? ModelsLog::STATUS_SUCCESS : ModelsLog::STATUS_ERROR;
    //             $logForDB['message'] = $message;
    //             ModelsLog::addLog($logForDB);

    //             return [
    //                 'status' => $status == 200,
    //                 'status_message' => $message,
    //                 'data' => $responseArray,
    //             ];
    //         } else {
    //             $logForDB['status'] = ModelsLog::STATUS_ERROR;
    //             $logForDB['trace'] = "$time Status: No Status Key";
    //             ModelsLog::addLog($logForDB);
    //             Log::channel($log)->debug("$time Status: No Status Key - Response: " . json_encode($responseArray));
    //             return [
    //                 'status' => false,
    //                 'status_message' => "No Status Key",
    //                 'data' => [],
    //             ];
    //         }
    //     } catch (\Exception $e) {
    //         $logForDB['status'] = ModelsLog::STATUS_ERROR;
    //         $logForDB['trace'] = "$time Status: Exception - " . $e->getMessage();
    //         ModelsLog::addLog($logForDB);
    //         Log::channel($log)->debug("$time Status: Exception - " . $e->getMessage());
    //         return [
    //             'status' => false,
    //             'status_message' => "Exception Error",
    //             'data' => [],
    //         ];
    //     }
    // }
    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $log_id = ModelsLog::addLog([
            'channel' => ModelsLog::CHANNEL_MegaH5,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ]);
        $logForDB = ['id' => $log_id];

        $log = 'MegaH5_api_records';
        if ($function == "PullLog" || $function == "FlagLog") {
            $log = 'MegaH5_api_ticket_records';
        }
        if ($function == "Deposit" || $function == "Withdraw") {
            $log = 'MegaH5_api_transfer_records';
        }
        if ($function == "CheckBalance") {
            $log = 'MegaH5_api_balance_records';
        }

        Log::channel($log)->debug("$time Function: " . $function);
        $this->create_param($function, $params);
        $params = $this->make_params($function);
        $url = $this->get_url($function);
        Log::channel($log)->debug("$time URL: " . $url);

        try {
            $client = new Client();

            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $params,
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
            ];

            $response = $client->request('POST', $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode == 200) {
                $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
                Log::channel($log)->debug("$time Status: OK");
                ModelsLog::addLog($logForDB);
                return [
                    'status' => true,
                    'status_message' => 'OK',
                    'data' => json_decode($response->getBody(), true),
                ];
            } else {
                $logForDB['status'] = ModelsLog::STATUS_ERROR;
                $logForDB['trace'] = "$time Status: Error - " . $statusCode;
                ModelsLog::addLog($logForDB);
                Log::channel($log)->debug("$time Status: Error - " . $statusCode);
                return [
                    'status' => false,
                    'status_message' => 'Error',
                    'data' => [],
                ];
            }
        } catch (\Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Exception - " . $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time Status: Exception - " . $e->getMessage());
            return [
                'status' => false,
                'status_message' => "Exception Error",
                'data' => [],
            ];
        }
    }



    public function encrytData($function)
    {
        $PrivateKey = config('api.MEGAH5_KEY');
        $string = '';
        switch ($function) {
            case 'CreatePlayer':
            case 'CheckBalance':
            case 'Deposit':
            case 'Withdraw':
            case 'GameLogin':
                if (!empty($this->PlayerId)) {
                    $string = $function . $this->RequestDateTime . $this->OperatorId . $PrivateKey . $this->PlayerId;
                } else {
                    Log::error("PlayerId is not set for function: $function");
                    return '';
                }
                break;

            case 'PullLog':
            case 'FlagLog':
            case 'GetTransactionDetails':
                $string = $function . $this->RequestDateTime . $this->OperatorId . $PrivateKey;
                break;

            default:
                Log::error("Unsupported function for encryption: $function");
                return '';
        }
        return md5($string);
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return 'en-US';
        }
        if (request()->lang == "cn") {
            return 'zh-CN';
        }
        return 'en-US';
    }
}
