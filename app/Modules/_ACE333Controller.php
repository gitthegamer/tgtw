<?php

namespace App\Modules;

use App\Helpers\DesEncrypt;
use App\Models\MemberAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _ACE333Controller
{
    protected $token;
    protected $accountID;
    protected $nickname;
    protected $currency;
    protected $playerID;
    protected $referenceID;
    protected $topUpAmount;
    protected $withdrawAmount;
    protected $timepoint;
    protected $q;
    protected $s;
    protected $accessToken;
    protected $datetime;


    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function make_params($function)
    {
        if ($function == "api/createplayer") {
            return [
                'accountID' => $this->accountID,
                'nickname' => $this->nickname,
                'currency' => $this->currency,
            ];
        }
        if ($function == "api/getbalance") {
            return [
                'currency' => $this->currency,
                'playerID' => $this->playerID,
            ];
        }
        if ($function == "api/LogOut") {
            return [
                'currency' => $this->currency,
                'playerID' => $this->playerID,
            ];
        }
        if ($function == "api/topup") {
            return [
                'playerID' => $this->playerID,
                'referenceID' => $this->referenceID,
                'topUpAmount' => $this->topUpAmount,
                'currency' => $this->currency,
            ];
        }
        if ($function == "api/withdraw") {
            return [
                'playerID' => $this->playerID,
                'referenceID' => $this->referenceID,
                'withdrawAmount' => $this->withdrawAmount,
                'currency' => $this->currency,
            ];
        }
        if ($function == "api/checkaccounttransaction3") {
            return [
                'currency' => $this->currency,
                'referenceID' => $this->referenceID,
            ];
        }
        if ($function == "api/gettimepoint") {
            return [
                'datetime' => $this->datetime,
            ];
        }
        if ($function == "api/CheckOrder") {
            return [
                'currency' => $this->currency,
                'referenceID' => $this->referenceID,
                'datetime' => $this->datetime,
                'timepoint' => $this->timepoint
            ];
        }
        if ($function == "api/accounttransactions3") {
            return [
                'timepoint' => $this->timepoint,
            ];
        }
        if ($function == "api/authenticate" || $function == "api/gamelist") {
            return [
                'q' => $this->q,
                's' => $this->s,
                'accessToken' => $this->accessToken,
            ];
        }
    }

    public function get_url($function)
    {
        if ($function == "api/authenticate") {
            return config('api.ACE333_H5_LOBBY_URL') . "api/Acc/Login/";
        }
        if ($function == "api/gamelist") {
            return config('api.ACE333_H5_LOBBY_URL') . "api/Game/GameList/";
        }
        return config('api.ACE333_URL') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _ACE333Controller();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_Ace333,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $log = 'ACE333_api_records';
        if ($function == "api/accounttransactions3" || $function == "api/gettimepoint") {
            $log = 'ACE333_api_ticket_records';
        }
        if ($function == "api/withdraw" || $function == "api/topup") {
            $log = 'ACE333_api_transfer_records';
        }
        if ($function == "api/getbalance") {
            $log = 'ACE333_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'token' => config('api.ACE333_API_TOKEN'),
                    'Accept' => 'text/plain',
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                }
            ];

            if ($function === 'api/getbalance' || $function === 'api/gamelist' || $function === 'api/accounttransactions3' || $function === 'api/gettimepoint' || $function === 'api/CheckOrder') {
                $method = "GET";
                $logForDB['method'] = $method;
                $options['query'] = $this->make_params($function);
                $response = $client->get($this->get_url($function), $options);
            } else {
                $method = "POST";
                $logForDB['method'] = $method;
                $options['body'] = json_encode($this->make_params($function));
                $response = $client->post($this->get_url($function), $options);
            }

            if ($function === 'api/accounttransactions3') {
                $response = $response->getBody()->getContents();
            } else {
                $response = @json_decode($response->getBody(), true);
            }
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

        if ($function == "api/authenticate") {
            if ($response['status'] !== "1") {
                $logForDB['status'] = ModelsLog::STATUS_ERROR;
                ModelsLog::addLog($logForDB);
            }

            return [
                'status' => $response['status'] == "1",
                'status_message' => $response['description'],
                'data' => $response
            ];
        }

        if ($function == "api/gettimepoint") {
            if (!isset($response['Timepoint'])) {
                $logForDB['status'] = ModelsLog::STATUS_ERROR;
                ModelsLog::addLog($logForDB);
            }

            return [
                'status' => isset($response['Timepoint']) ? true : false,
                'status_message' => "Timepoint",
                'data' => $response['Timepoint'] ?? null
            ];
        }

        if ($function == "api/accounttransactions3") {
            $dataLines = explode("\r\n", $response);
            // Skip the first two lines (header)
            array_shift($dataLines); // Remove the first line
            array_shift($dataLines); // Remove the second line

            $data = [];
            foreach ($dataLines as $line) {
                $dataFields = $this->convertStringToJson($line);
                $data[] = json_decode($dataFields, true);
            }

            return [
                'status' => !empty($data),
                'status_message' => !empty($data) ? "Have Transactions" : "No Account Transactions",
                'data' => $data
            ];
        }

        if (!isset($response['error'])) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => isset($response['error']) ? $response['error'] == 0 : false,
            'status_message' => $response['description'] ?? null,
            'data' => $response
        ];
    }

    public static function callback()
    {
        Log::channel('ACE333_api_login_records')->debug("callback start---");
        Log::channel('ACE333_api_login_records')->debug("request : " . request()->getContent());
        try {
            $member = MemberAccount::where('username', request('userName'))->where('password', request('password'))->first();
            if ($member) {
                $response = [
                    "playerID" => $member->account,
                    "balance" => $member->balance(),
                    "error" => "0",
                    "description" => SELF::OPERATOR_ERRORS[0],
                ];
            } else {
                $response = [
                    "playerID" => "",
                    "balance" => "",
                    "error" => "2",
                    "description" => SELF::OPERATOR_ERRORS[2],
                ];
            }
        } catch (Exception $e) {
            $response = [
                "playerID" => "",
                "balance" => "",
                "error" => 100,
                "description" => SELF::OPERATOR_ERRORS[100],
            ];
            Log::channel('ACE333_api_login_records')->error("Error: " . $e->getMessage());
            Log::channel('ACE333_api_login_records')->error("Stack Trace: " . $e->getTraceAsString());
        }

        Log::channel('ACE333_api_login_records')->debug("response : " . json_encode($response));
        Log::channel('ACE333_api_login_records')->debug("callback end");

        header('Content-Type: application/json');

        return response()->json($response);
    }

    public static function encryptedString($function, $data)
    {
        $secretKey = config('api.ACE333_H5_SECRET_KEY');
        $md5Key = config('api.ACE333_H5_MD5_KEY');
        $encryptKey = config('api.ACE333_H5_ENCRYPT_KEY');
        $delimiter = config('api.ACE333_DELIMITER');
        $currTime = Carbon::now('UTC')->format('YmdHis');
        $QS = "";

        if ($function == "api/authenticate") {
            $QS = "key=" . $secretKey . $delimiter . "time=" . $currTime . $delimiter . "userName=" . $data->userName . $delimiter . "password=" . $data->password . $delimiter . "currency=" . $data->currency . $delimiter . "nickName=" . $data->nickName;
        }

        if ($function == "api/gamelist") {
            $QS = "key=" . $secretKey . $delimiter . "time=" . $currTime . $delimiter . "gameType=" . $data->gameType;
        }

        $q = urlencode(DesEncrypt::cbc_encrypt($QS, $encryptKey));
        $s = md5($QS . $md5Key . $currTime . $secretKey);

        return ['q' => $q, 's' => $s];
    }

    public function convertStringToJson($csv_string)
    {
        $headers = [
            "playerID",
            "extPlayerID",
            "gameID",
            "methodType",
            "playSessionID",
            "referenceID",
            "status",
            "created",
            "updated",
            "betAmount",
            "winAmount",
            "jackpotModule",
            "jackpotContributionAmt",
            "currency",
            "resultUrl",
            "roundDetails",
            "platform"
        ];

        // Split the line using str_getcsv, which will handle quoted values properly
        $values = str_getcsv($csv_string);

        // Combine the headers with the values to create an associative array
        $assoc_array = array_combine($headers, $values);

        // Convert the associative array to JSON
        $json_string = json_encode($assoc_array, JSON_PRETTY_PRINT);

        return $json_string;
    }

    const OPERATOR_ERRORS = [
        0 => 'Success',
        1 => 'Insufficient balance. The error should be returned in the response on the Bet request.',
        2 => 'Player not found. Should be returned in the response on any request sent by PROVIDER if the issue occurred.',
        3 => 'Bet is not allowed. Should be returned in any case when the player is not allowed to play a specific game. For example, because of special bonus.',
        4 => 'Player authentication failed due to invalid, not found or expired token. Should be returned in the response on Authentication request.',
        6 => 'Player is frozen. OPERATOR will return this error in the response of any request if player account if banned or frozen.',
        8 => 'Game is not found or disabled. This error should be returned on Bet request if the game cannot be played by some reason. Bet result request with winning amount should be processed as intended, even if the game is disabled.',
        100 => 'Internal server error. OPERATOR will return this error code if their system has internal problem and cannot process the request.',
    ];

    const GAME_TYPE = [
        'Slot Game' => 1,
        'Arcade Game' => 2,
        'Table Game' => 3,
        'Fishing Game' => 4,
        'Marvel slot' => 5,
        'Classic Slot Game' => 7,
        'External Game' => 8,
        'Multiplayer' => 9,
    ];
}
