<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;
use App\Services\CsvHelper;
use App\Services\CsvService;
use Illuminate\Support\Facades\Cache;

class _PPLiveController
{

    const ERRORS = [
        "0" => "Success",
    ];

    protected $secureLogin;
    protected $externalPlayerId;
    protected $currency;
    protected $hash;
    protected $externalTransactionId;
    protected $amount;
    protected $categories;
    protected $gameID;
    protected $language;
    protected $password;
    //protected $timepoint;
    protected $login;
    protected $timepoint;
    protected $dataType;

    public static function init($function, $params)
    {
        $controller = new _PPLiveController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }

        $this->hash = $this->createHash($function);
    }

    public function get_url($function)
    {
        if ($function == "/CasinoGameAPI/getLobbyGames") {
            return config('api.PPLIVE_LOBBY_LINK') . $function;
        }
        if ($function == "/DataFeeds/transactions/" || $function == "/DataFeeds/gamerounds/") {
            return config('api.PPLIVE_BETLOGS_LINK') . $function;
        }

        return config('api.PPLIVE_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "/player/account/create/":
                return [
                    'secureLogin' => $this->secureLogin,
                    'externalPlayerId' => $this->externalPlayerId,
                    'currency' => $this->currency,
                    'hash' => $this->hash,
                ];
            case "/balance/current/";
                return [
                    'secureLogin' => $this->secureLogin,
                    'externalPlayerId' => $this->externalPlayerId,
                    'hash' => $this->hash,
                ];
            case "/balance/transfer/":
                return [
                    'secureLogin' => $this->secureLogin,
                    'externalPlayerId' => $this->externalPlayerId,
                    'externalTransactionId' => $this->externalTransactionId,
                    'amount' => $this->amount,
                    'hash' => $this->hash,
                ];
            case "/balance/transfer/transactions":
                return [
                    'secureLogin' => $this->secureLogin,
                    'hash' => $this->hash,
                ];
            case "/getCasinoGames":
                return [
                    'secureLogin' => $this->secureLogin,
                    'hash' => $this->hash,
                ];
            case "/game/start/":
                return [
                    'secureLogin' => $this->secureLogin,
                    'externalPlayerId' => $this->externalPlayerId,
                    'gameId' => $this->gameID,
                    'language' => $this->language,
                    'hash' => $this->hash,
                ];
            case "/DataFeeds/transactions/":
                return [
                    'login' => $this->login,
                    'password' => $this->password,
                    'timepoint' => $this->timepoint,
                    'dataType' => $this->dataType,
                ];
            case "/DataFeeds/gamerounds/":
                return [
                    'login' => $this->login,
                    'password' => $this->password,
                    'timepoint' => $this->timepoint,
                    'dataType' => $this->dataType,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = ($function == "/DataFeeds/transactions/" || $function == "/DataFeeds/gamerounds/") ? "get" : "post";
        $logForDB =   [
            'channel' => ModelsLog::CHANNEL_PPLive,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $log = 'pplive_api_records';
        if ($function == "/DataFeeds/transactions/" || $function == "/DataFeeds/gamerounds/") {
            $log = 'pplive_api_ticket_records';
        }
        if ($function == "/balance/transfer/" || $function == "/balance/transfer/transactions") {
            $log = 'pplive_api_transfer_records';
        }
        if ($function == "/balance/current/") {
            $log = 'pplive_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->$method($this->get_url($function), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'query' => $this->make_params($function),
            ]);

            if ($function === '/DataFeeds/transactions/' || $function === '/DataFeeds/gamerounds/') {
                $response = $response->getBody()->getContents();
            } else {
                $response = @json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid JSON response: " . json_last_error_msg());
                }
            }

            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            ModelsLog::addLog($logForDB);
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

        if ($function === '/DataFeeds/transactions/' || $function === '/DataFeeds/gamerounds/') {
            // echo $response;
            $dataLines = explode("\n", $response);
            // Extract timepoint from the first line and store it in cache if it's valid
            $timepointLine = array_shift($dataLines);
            if (strpos($timepointLine, 'timepoint=') === 0) {
                $timepoint = substr($timepointLine, strlen('timepoint='));
                Cache::put('pplive_timepoint', $timepoint);
                Log::debug("Stored timepoint in cache: " . $timepoint);
            } else {
                Log::debug("Invalid timepoint format: " . $timepointLine);
            }

            // Skip the second line (header)
            array_shift($dataLines);

            $data = [];
            foreach ($dataLines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $dataFields = $this->convertStringToJson($line);
                $data[] = json_decode($dataFields, true);
            }

            return [
                'status' => !empty($data),
                'status_message' => !empty($data) ? "Have Transactions" : "No Account Transactions",
                'data' => $data
            ];
        }

        if ($response['error'] != "0") {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $response['error'];
            ModelsLog::addLog($logForDB);
            return [
                'status' => false,
                'status_message' => $response['error'],
                'data' => $response['error']
            ];
        }

        return [
            'status' => (($response['error'] == "0")),
            'status_message' => "",
            'data' => $response
        ];
    }

    public function convertStringToJson($csv_string)
    {
        $headers = [
            "playerID",
            "extPlayerID",
            "gameID",
            "playSessionID",
            "parentSessionID",
            "startDate",
            "endDate",
            "status",
            "type",
            "bet",
            "win",
            "currency",
            "jackpot"
        ];

        // Split the line using str_getcsv, which will handle quoted values properly
        $values = str_getcsv($csv_string);

        if (count($values) != count($headers)) {
            return null;
        }

        // Combine the headers with the values to create an associative array
        $assoc_array = array_combine($headers, $values);
        // Convert the associative array to JSON
        $json_string = json_encode($assoc_array, JSON_PRETTY_PRINT);
        return $json_string;
    }

    public function handleCSVResponse($response, $data)
    {

        if (is_string($response)) {
            // Handle response as a CSV string
            $lines = explode("\n", $response);

            // Check if there are enough lines to process
            if (count($lines) < 2) {
                return [
                    'status' => false,
                    'status_message' => 'Invalid response format',
                    'data' => []
                ];
            }

            // Extract timepoint from the first line
            $timepointLine = array_shift($lines);
            parse_str($timepointLine, $timepointArray);
            $timepoint = $timepointArray['timepoint'] ?? null;
            // Log the extracted timepoint
            Cache::put('timepoint', $timepoint);
            $csvData = implode("\n", $lines);
            $lines = explode("\n", trim($csvData));
            $headers = str_getcsv(array_shift($lines));
            // Check if headers are valid
            if (empty($headers)) {
                return [
                    'status' => false,
                    'status_message' => 'Invalid CSV headers',
                    'data' => []
                ];
            }

            $data = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $values = str_getcsv($line);
                    if (count($values) === count($headers)) { // Ensure correct number of values
                        $dataFields = array_combine($headers, $values);
                        $data[] = $dataFields;
                    } else {
                        Log::debug("CSV line has mismatched columns: $line");
                    }
                }
            }
        } elseif (is_array($response)) {
            // Handle response as JSON array
            $data = $response;
            $timepoint = $response['timepoint'] ?? null;
        } else {
            return [
                'status' => false,
                'status_message' => 'Invalid response format',
                'data' => []
            ];
        }
        return [
            'status' => !empty($data),
            'status_message' => !empty($data) ? "Have Transactions" : "No Account Transactions",
            'data' => $data,
        ];
    }


    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "cn";
        }
        return "en";
    }

    public function createHash($function)
    {
        $param = $this->make_params($function);
        //remove hash in array
        unset($param["hash"]);
        ksort($param);
        $paramString = '';
        foreach ($param as $key => $value) {
            $paramString .= "{$key}={$value}&";
        }

        // Remove the last '&' character
        $paramString = rtrim($paramString, '&');
        $paramString .= config("api.PPLIVE_SECRET_KEY");
        $hash = md5($paramString);
        return ($hash);
    }
}
