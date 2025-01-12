<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Illuminate\Support\Facades\Log;
use App\Models\Product;

class _PlaytechController
{
    protected $api_key;
    protected $playername;
    protected $adminname;
    protected $entityname;
    protected $password;
    protected $custom02;
    protected $externaltranid;
    protected $externaltransactionid;
    protected $startdate;
    protected $enddate;
    protected $page;
    protected $amount;

    public static function init($function, $params)
    {
        $controller = new _PlaytechController();
        return $controller->request($function, $params);
    }

    public function get_url($function)
    {
        return config('api.PT_LINK') . $function;
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function make_params($function)
    {
        if ($function == "player/create") {
            return [
                "playername" => $this->playername,
                "adminname" => $this->adminname,
                "entityname" => $this->entityname,
                "password" => $this->password,
                "custom02" => $this->custom02,
            ];
        }
        if ($function == "player/balance") {
            return [
                "playername" => $this->playername,
            ];
        }
        if ($function == "player/deposit") {
            return [
                "playername" => $this->playername,
                "amount" => $this->amount,
                "adminname" => $this->adminname,
                "externaltranid" => $this->externaltranid,
            ];
        }
        if ($function == "player/withdraw") {
            return [
                "playername" => $this->playername,
                "amount" => $this->amount,
                "adminname" => $this->adminname,
                "isForce" => true,
                "externaltranid" => $this->externaltranid,
                "losebonus" => true,
            ];
        }
        if ($function == "player/checktransaction") {
            return [
                "externaltransactionid" => $this->externaltransactionid,
            ];
        }
        if ($function == "game/flow") {
            return [
                "exitgame" => false,
                "showdetailedinfo" => true,
                "startdate" => $this->startdate,
                "enddate" => $this->enddate,
                "page" => $this->page,
                "perPage" => 1000,
            ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_PLAYTECH,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
        

        $log = 'playtech_api_records';
        if ($function == "game/flow") {
            $log = 'playtech_api_ticket_records';
        }
        if ($function == "player/deposit" || $function == "player/withdraw" || $function == "player/checktransaction") {
            $log = 'playtech_api_transfer_records';
        }
        if ($function == "player/balance") {
            $log = 'playtech_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);
        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));

        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
            ]);

            $response = $client->post($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'form_params' => $this->make_params($function),
                'headers' => $headers = [
                    'X_ENTITY_KEY' => $this->api_key,
                ],
                'cert' => app_path("Playtech/MYR/MYR.pem"),
                'ssl_key' => [app_path("Playtech/MYR/MYR.key"), "c4UTw8fKKvXnVrl6"],
                'http_errors' => false,
                // 'curl' => [
                //     CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_1
                // ],
            ]);
            
            Log::channel($log)->debug("$time Header: " . json_encode($headers));

            $response = @json_decode($response->getBody()->getContents(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);

            Log::channel($log)->debug("$time Response: " . json_encode($response));
        } catch (\Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            return [
                'status' => false,
                'code' => -1,
                'message' => "Unknown ERROR",
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
                'code' => -1,
                'message' => "Connection Error",
                'data' => null,
            ];
        }

        if (isset($response['error'])) {
            $logForDB['message'] = $response['error'];
            Log::channel($log)->debug("$time Status: " . $response['error']);
        } else {
            Log::channel($log)->debug("$time Status: OK");
        }

        if(isset($response['error'])){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }



        if ($function == "game/flow") {
            return [
                'status' => (!isset($response['error'])) ? true : false,
                'code' => (!isset($response['errorcode'])) ? 1 : $response['errorcode'],
                'message' => (!isset($response['error'])) ? "OK" : $response['error'],
                'data' => (!isset($response['result'])) ? [] : $response['result'],
                'pagination' => (!isset($response['pagination'])) ? [] : $response['pagination'],
            ];
        }

        return [
            'status' => (!isset($response['error'])) ? true : false,
            'code' => (!isset($response['errorcode'])) ? 1 : $response['errorcode'],
            'message' => (!isset($response['error'])) ? "OK" : $response['error'],
            'data' => (!isset($response['result'])) ? [] : $response['result'],
        ];
    }

    public static function getTypeFromType($category)
    {
        if (in_array($category, [
            "Card Games",
            "Fixed Odds",
            "Mini games",
            "POP Slots",
            "POP Arcade",
            "Arcade",
            "POP Jackpot Slots",
            "POP Scratch cards",
            "Progressive Slot Machines",
            "Slot Machines",
            "Table Games",
            "Video Pokers",
        ])) {
            return Product::CATEGORY_SLOTS;
        }

        if (in_array($category, [
            "baccarat_sicbo",
            "blackjack",
            "gameshows",
            "poker",
            "Poker",
            "roulette",
            "Live Games",
        ])) {
            return Product::CATEGORY_LIVE;
        }
    }

    public static function getProductCategoryFromType($category, $product)
    {
        if (SELF::getTypeFromType($category) == Product::CATEGORY_SLOTS) {
            if ($product->product_code != "PPS") {
                return $product->product_code;
            }
            return "PPS";
        }
        if (SELF::getTypeFromType($category) == Product::CATEGORY_LIVE) {
            return "PPL";
        }

        return "";
    }

    public static function getGameCategoryFromType($category)
    {
        if (SELF::getTypeFromType($category) == Product::CATEGORY_SLOTS) {
            return "CATEGORY_SLOTS";
        }
        if (SELF::getTypeFromType($category) == Product::CATEGORY_LIVE) {
            return "Live Casino";
        }

        return "";
    }
}
