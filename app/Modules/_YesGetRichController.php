<?php

namespace App\Modules;

use App\Models\ProviderLog;
use DateTime;
use DateTimeZone;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;
use App\Helpers\_YesGetRich;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class _YesGetRichController
{
    protected $Account;
    protected $Accounts;
    protected $AgentId;
    protected $AgentKey;
    protected $keyG;
    protected $data;
    protected $Amount;
    protected $TransactionId;
    protected $TransferType;
    protected $GameId;
    protected $Lang;
    protected $Page;
    protected $StartTime;
    protected $EndTime;
    protected $PageLimit;
    protected $Key;


    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }

        $this->AgentId = config('api.YGR_AGENT_ID');
        $this->AgentKey = config('api.YGR_AGENT_KEY');
        $this->keyG = md5(Carbon::now()->format('ymj') . $this->AgentId . $this->AgentKey);
        $this->Key = $this->encrytData($function);
    }
    public function make_params($function)
    {
        switch ($function) {
            case "/CreateMember":
                return [
                    'Account' => $this->Account,
                    'AgentId' => $this->AgentId,
                    'Key' => $this->Key
                ];
            case "/GetMemberInfo":
                return [
                    'Accounts' => $this->Accounts,
                    'AgentId' => $this->AgentId,
                    'Key' => $this->Key
                ];
            case "/Transfer":
                return [
                    'Account' => $this->Account,
                    'TransactionId' => $this->TransactionId,
                    'Amount' => $this->Amount,
                    'TransferType' => $this->TransferType,
                    'AgentId' => $this->AgentId,
                    'Key' => $this->Key
                ];
            case "/CheckTransfer":
                return [
                    'TransactionId' => $this->TransactionId,
                    'AgentId' => $this->AgentId,
                    'Key' => $this->Key
                ];
            case "/GameList":
                return [
                    'AgentId' => $this->AgentId,
                    'Key' => $this->Key
                ];
            case "/Login":
                return [
                    'Account' => $this->Account,
                    'GameId' => $this->GameId,
                    'Lang' => $this->Lang,
                    'AgentId' => $this->AgentId,
                    'Key' => $this->Key
                ];
            case "/GetBetRecordByDateTime":
                return [
                    'StartTime' => $this->StartTime,
                    'EndTime' => $this->EndTime,
                    'Page' => $this->Page,
                    'PageLimit' => $this->PageLimit,
                    'AgentId' => $this->AgentId,
                    'Key' => $this->Key
                ];
            default:
                return [];
        }
    }

    public function make_encryted_params($function)
    {
        if ($function == '/GameList') {
            return [
                'AgentId' => $this->AgentId,
                'Key' => $this->Key
            ];
        }
        return [
            'Account' => $this->Account ?? null,
            'TransactionId' => $this->TransactionId ?? null,
            'Amount' => $this->Amount ?? null,
            'TransferType' => $this->TransferType ?? null,
            'AgentId' => $this->AgentId,
            'Key' => $this->Key,
        ];
    }


    public function get_url($function)
    {
        $baseUrl = config('api.YGR_LINK');
        $url = $baseUrl . $function;

        if ($function == '/Login') {
            $url .= '?' . http_build_query([
                'Account' => $this->Account,
                'GameId' => $this->GameId,
                'Lang' => $this->Lang,
                'AgentId' => $this->AgentId,
                'Key' => $this->Key
            ]);
        };
        log::debug($url);
        return $url;
    }


    public static function init($function, $params)
    {
        $controller = new _YesGetRichController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        Log::debug("Request Function: $function");
        Log::debug("Parameters Sent: " . json_encode($params));
        $time = time();
        $method = ($function == "/Login") ? "get" : "post";
        $log_id = ModelsLog::addLog([
            'channel' => ModelsLog::CHANNEL_YGR,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ]);
        $logForDB = ['id' => $log_id];

        $log = 'YGR_api_records';
        if ($function == "/GetBetRecordByDateTime") {
            $log = 'YGR_api_ticket_records';
        }
        if ($function == "/Transfer" || $function == "/CheckTransfer") {
            $log = 'YGR_api_transfer_records';
        }
        if ($function == "/GetMemberInfo") {
            $log = 'YGR_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        Log::channel($log)->debug("$time Params : " . json_encode($params));

        $this->create_param($function, $params);
        $params = $this->make_params($function);
        $url = $this->get_url($function);
        log::debug($url);
        if ($function == '/Login') {

            return [
                'status' => true,
                'status_message' => "",
                'data' =>  $url,
            ];
        }

        Log::channel($log)->debug("$time URL: " . $url);
        try {
            $client = new Client();

            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => $function == '/Login' ? $params : [],
                'json' => $function != '/Login' ? $this->make_encryted_params($function) : [],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
            ];
            if ($method == 'get') {
                $options['query'] = $params;
            } else {
                $options['json'] = $params;
            }
            $response = $client->request($method, $url, $options);

            if ($function == '/GetBetRecordByDateTime') {
                Log::channel($log)->debug("$time Response for GetBetRecordByDateTime: " . $response->getBody());
            }

            Log::channel($log)->debug("$time Response: " . $response->getBody());

            $responseArray = json_decode($response->getBody(), true);

            return [
                'status' => ($responseArray['ErrorCode'] == 0 || $responseArray['ErrorCode'] == 3),
                'status_message' => "",
                'data' => $responseArray,
            ];
        } catch (Exception $e) {
            Log::channel($log)->debug("$time " . "Unknown ERROR: " . $e->getMessage());
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }
    }



    public function encrytData($function)
    {
        switch ($function) {
            case '/CreateMember':
                $queryString = "Account={$this->Account}&AgentId={$this->AgentId}";
                break;
            case '/GetMemberInfo':
                $queryString = "Accounts={$this->Accounts}&AgentId={$this->AgentId}";
                break;
            case '/Transfer':
                $queryString = "Account={$this->Account}&TransactionId={$this->TransactionId}&Amount={$this->Amount}&TransferType={$this->TransferType}&AgentId={$this->AgentId}";
                break;
            case '/CheckTransfer':
                $queryString = "TransactionId={$this->TransactionId}&AgentId={$this->AgentId}";
                break;
            case '/Login':
                $queryString = "Account={$this->Account}&GameId={$this->GameId}&Lang={$this->Lang}&AgentId={$this->AgentId}";
                break;
            case '/GetBetRecordByDateTime':
                $queryString = "StartTime={$this->StartTime}&EndTime={$this->EndTime}&Page={$this->Page}&PageLimit={$this->PageLimit}&AgentId={$this->AgentId}";
                break;
            case '/GameList':
                $queryString = "AgentId={$this->AgentId}";
                break;
            default:
                Log::error("Unsupported function for encryption: $function");
                $queryString = '';
        }
        $prefix = self::randomGenerateCharacter();
        $suffix = self::randomGenerateCharacter();
        $queryString .= $this->keyG;
        $hash = md5($queryString);
        $Key = "{$prefix}{$hash}{$suffix}";
        return $Key;
    }

    public function getGameListJson()
    {
        $games = _YesGetRich::getGameList();

        return response()->json($games);
    }


    public function randomGenerateCharacter()
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        // Shuffle the characters and take the first 6
        $randomString = substr(str_shuffle($characters), 0, 6);
        return $randomString;
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
