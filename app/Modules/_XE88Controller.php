<?php

namespace App\Modules;

use Carbon\Carbon;
use App\Models\Log as ModelsLog;
use Illuminate\Support\Facades\Log;

class _XE88Controller
{
    const ERRORS = [
        "player/create" => [
            0 => "Success",
            31 => "Player name already exists",
            33 => "Kiosk Admin is disabled, contact to company",
            34 => "Access is denied, contact to company",
            50 => "Player password is too short",
            51 => "Player password is too long",
            52 => "Player password is not match with rule",
            72 => "Could not load data. Error: 'Service error accessing API'",
            73 => "Could not load data from database. Error: 'Database error occurred, please contact support'. Please try again later",
        ],
        "player/info" => [
            0 => "Success",
            33 => "Kiosk Admin is disabled, contact to company",
            34 => "Access is denied, contact to company",
            41 => "Player does not exist",
            42 => "Player is frozen",
            50 => "Player password is too short",
            51 => "Player password is too long",
            52 => "Player password is not match with rule",
            72 => "Could not load data. Error: 'Service error accessing API'",
            73 => "Could not load data from database. Error: 'Database error occurred, please contact support'. Please try again later",
        ],
        "player/deposit" => [
            0 => "Success",
            33 => "Kiosk Admin is disabled, contact to company",
            34 => "Access is denied, contact to company",
            35 => "Kiosk admin doesn’t have enough balance to deposit, please deposit first",
            37 => "The possible values of amount can be only numbers",
            38 => "Cannot make deposit, Amount is less than minimum deposit amount for this player",
            41 => "Player does not exists",
            42 => "Player is frozen",
            72 => "Could not load data. Error: 'Service error accessing API'",
            73 => "Could not load data from database. Error: 'Database error occured, please contact support'. Please try again later",
        ],
        "player/withdraw" => [
            0 => "Success",
            33 => "Kiosk Admin is disabled, contact to company",
            34 => "Access is denied, contact to company",
            36 => "Can’t withdraw, because player is playing gamenow.",
            37 => "The possible values of amount can be only numbers",
            39 => "Cannot make withdraw, Amount is not bigger than current balance.",
            41 => "Player does not exists",
            42 => "Player is frozen",
            72 => "Could not load data. Error: 'Service error accessing API'",
            73 => "Could not load data from database. Error: 'Database error occured, please contact support'. Please try again later",
        ],
        "player/checktransaction" => [
            0 => "Success",
            33 => "Kiosk Admin is disabled, contact to company",
            34 => "Access is denied, contact to company",
            41 => "Player does not exists",
            45 => "Transaction can’t find",
            72 => "Could not load data. Error: 'Service error accessing API'",
        ],
        "customreport/playergamelog" => [
            0 => "Success",
            33 => "Kiosk Admin is disabled, contact to company",
            34 => "Access is denied, contact to company",
            41 => "Player does not exists",
            46 => "Date Time is not valid",
            72 => "Could not load data. Error: 'Service error accessing API'",
            73 => "Could not load data from database. Error: 'Database error occurred, please contact support'. Please, try again later",
            75 => "Could not load data, because of too frequent access, your IP is blocked and please contact custom service.",
            76 => "Could not load data, because call interval is exceed half an hour",
        ]
    ];

    protected $key;
    protected $prefix;
    protected $agentid;
    protected $account;
    protected $password;
    protected $amount;
    protected $trackingid;
    protected $date;
    protected $page;
    protected $signature_key;
    protected $starttime;
    protected $endtime;
    protected $perpage;
    

    public static function init($function, $params)
    {
        $controller = new _XE88Controller();
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
        return config('api.XE88_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "player/create":
                return [
                    'agentid' => $this->agentid,
                    'account' => $this->account,
                    'password' => $this->password,
                ];
            case "player/info":
                return [
                    'agentid' => $this->agentid,
                    'account' => $this->account,
                ];
            case "player/deposit":
                return [
                    'agentid' => $this->agentid,
                    'account' => $this->account,
                    'amount' => $this->amount,
                    'trackingid' => $this->trackingid,
                ];
            case "player/withdraw":
                return [
                    'agentid' => $this->agentid,
                    'account' => $this->account,
                    'amount' => $this->amount,
                    'trackingid' => $this->trackingid,
                ];
            case "player/checktransaction":
                return [
                    'agentid' => $this->agentid,
                    'trackingid' => $this->trackingid,
                ];
            case "customreport/playergamelog":
                return [
                    'agentid' => $this->agentid,
                    'account' => $this->account,
                    "date" => $this->date,
                    "starttime" => $this->starttime,
                    "endtime" => $this->endtime,
                    'page' => $this->page,
                    'perpage' => $this->perpage,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_XE88,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
       

        $log = 'xe88_api_records';
        if ($function == "customreport/playergamelog") {
            $log = 'xe88_api_ticket_records';
        }
        if ($function == "player/withdraw" || $function == "player/deposit" || $function == "player/checktransaction") {
            $log = 'xe88_api_transfer_records';
        }
        if ($function == "player/info") {
            $log = 'xe88_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        $params = json_encode($this->make_params($function));
        $signature = base64_encode(hash_hmac("sha256", $params, $this->signature_key, true));
        Log::channel($log)->debug("$time Signature : " . $signature);
        Log::channel($log)->debug("$time Signature Key : " . $this->signature_key);

        $ch = curl_init($this->get_url($function));

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'hashkey: ' . $signature
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $res = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }
        Log::channel($log)->debug("$time Status Code : " . curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        Log::channel($log)->debug("$time Response : " . $res);
        $response = @json_decode($res, true);

        $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
        $logForDB['trace'] = json_encode($response);

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

        if (isset($response['message'])) {
            $message = $response['message'];
        } elseif (isset(SELF::ERRORS[$function][$response['code']])) {
            $message = SELF::ERRORS[$function][$response['code']];
        } else {
            $message = "Unknown Error";
        }

        $logForDB['message'] = $message;
        
        Log::channel($log)->debug("$time Status Message: $message");

        if($response['code'] !== 0){
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        return [
            'status' => ($response['code'] == 0) ? true : false,
            'status_message' => $message,
            'data' => $response
        ];
    }

    public static function generate_username()
    {
        return random_int(100000, 9999999);
    }
}
