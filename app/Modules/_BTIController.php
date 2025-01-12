<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _BTIController
{
    protected $AgentUserName;
    protected $AgentPassword;
    protected $MerchantCustomerCode;
    protected $LoginName;
    protected $CurrencyCode;
    protected $CountryCode;
    protected $City;
    protected $FirstName;
    protected $LastName;
    protected $Group1ID;
    protected $CustomerDefaultLanguage;
    protected $CustomerMoreInfo;
    protected $DomainID;
    protected $DateOfBirth;
    protected $Amount;
    protected $RefTransactionCode;
    protected $BonusCode;
    protected $From;
    protected $To;

    public static function init($function, $params)
    {
        $controller = new _BTIController();
        return $controller->request($function, $params);
    }

    public function create_param($params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function get_url($function)
    {
        return config('api.BTI_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "CreateUserNew":
                return [
                    'AgentUserName' => $this->AgentUserName,
                    'AgentPassword' => $this->AgentPassword,
                    'MerchantCustomerCode' => $this->MerchantCustomerCode,
                    'LoginName' => $this->LoginName,
                    'CurrencyCode' => $this->CurrencyCode,
                    'CountryCode' => $this->CountryCode,
                    'City' => $this->City,
                    'FirstName' => $this->FirstName,
                    'LastName' => $this->LastName,
                    'Group1ID' => $this->Group1ID,
                    'CustomerDefaultLanguage' => $this->CustomerDefaultLanguage,
                    'CustomerMoreInfo' => $this->CustomerMoreInfo,
                    'DomainID' => $this->DomainID,
                    'DateOfBirth' => $this->DateOfBirth
                ];
            case "GetBalance":
                return [
                    'AgentUserName' => $this->AgentUserName,
                    'AgentPassword' => $this->AgentPassword,
                    'MerchantCustomerCode' => $this->MerchantCustomerCode
                ];
            case "TransferToWHL":
                return [
                    'AgentUserName' => $this->AgentUserName,
                    'AgentPassword' => $this->AgentPassword,
                    'MerchantCustomerCode' => $this->MerchantCustomerCode,
                    'Amount' => $this->Amount,
                    'RefTransactionCode' => $this->RefTransactionCode,
                    'BonusCode' => $this->BonusCode
                ];
            case "TransferFromWHL":
                return [
                    'AgentUserName' => $this->AgentUserName,
                    'AgentPassword' => $this->AgentPassword,
                    'MerchantCustomerCode' => $this->MerchantCustomerCode,
                    'Amount' => $this->Amount,
                    'RefTransactionCode' => $this->RefTransactionCode
                ];
            case "GetCustomerAuthToken":
                return [
                    'AgentUserName' => $this->AgentUserName,
                    'AgentPassword' => $this->AgentPassword,
                    'MerchantCustomerCode' => $this->MerchantCustomerCode
                ];
            case "bettinghistory":
                return [
                    'AgentUserName' => $this->AgentUserName,
                    'AgentPassword' => $this->AgentPassword,
                    'From' => $this->From,
                    'To' => $this->To,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_BTI,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
        

        $this->create_param($params);
        $url = $this->get_url($function);

        try {
            $client = new \GuzzleHttp\Client();
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'WEB_LIB_GI_' . config('api.AG_CAGENT'),
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($function) {
                },
                'body' => json_encode($this->make_params($function)),
            ];
            $response = $client->post($url, $options);

            $response = @json_decode(json_encode(simplexml_load_string($response->getBody())), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            return [
                'status' => false,
                'status_message' => $e->getMessage(),
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

        if($response['errCode'] !== "0"){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => $response['errCode'] == "0",
            'status_message' => $response['errMsg'] ?? "no message",
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
        if (request()->lang == "bm") {
            return "ms";
        }
        return "en";
    }
}
