<?php

namespace App\Modules;

use App\Http\Helpers;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _WBetController
{
    const ERRORS = [
        0 => "Success",
        3 => "Parameter Error",
        4 => "Signature Error",
    ];

    protected $operatorcode;
    protected $providercode;
    protected $username;
    protected $password;
    protected $referenceid;
    protected $type;
    protected $amount;
    protected $html5;
    protected $signature;
    protected $reformatJson;
    protected $secretkey;
    protected $versionkey;
    protected $ticket;

    public static function init($function, $params)
    {
        $controller = new _WBetController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {

        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->signature = $this->encypt_to_token($function);
    }

    public function get_url($function)
    {
        if ($function == 'fetchbykey.aspx') {
            return config('api.WBET_REPORT_LINK') . $function;
        }
        return config('api.WBET_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "createMember.aspx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'username' => $this->username,
                    'signature' => $this->signature,
                ];
            case "checkMemberProductUsername.aspx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'providercode' => $this->providercode,
                    'username' => $this->username,
                    'signature' => $this->signature,
                ];
            case "getBalance.aspx";
                return [
                    'operatorcode' => $this->operatorcode,
                    'providercode' => $this->providercode,
                    'username' => $this->username,
                    'password' => $this->password,
                    'signature' => $this->signature,
                ];
            case "makeTransfer.aspx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'providercode' => $this->providercode,
                    'username' => $this->username,
                    'password' => $this->password,
                    'referenceid' => $this->referenceid,
                    'type' => $this->type,
                    'amount' => $this->amount,
                    'signature' => $this->signature,
                ];
            case "checkTransaction.ashx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'referenceid' => $this->referenceid,
                    'signature' => $this->signature,
                ];
            case "launchGames.aspx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'providercode' => $this->providercode,
                    'username' => $this->username,
                    'password' => $this->password,
                    'type' => $this->type,
                    'html5' => $this->html5,
                    'signature' => $this->signature,
                ];
            case "getGameList.aspx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'providercode' => $this->providercode,
                    'reformatJson' => $this->reformatJson,
                    'signature' => $this->signature,
                ];
            case "fetchbykey.aspx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'versionkey' => $this->versionkey,
                    'signature' => $this->signature,
                ];
            case "markbyjson.aspx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'ticket' => $this->ticket,
                    'signature' => $this->signature,
                ];
            case "checkAgentCredit.aspx":
                return [
                    'operatorcode' => $this->operatorcode,
                    'signature' => $this->signature,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_WBET,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $log = 'playboy_api_records';
        if ($function == "fetchbykey.aspx" || $function == "markbyjson.aspx") {
            $log = 'playboy_api_ticket_records';
        }
        if ($function == "makeTransfer.aspx" || $function == "checkTransaction.ashx") {
            $log = 'playboy_api_transfer_records';
        }
        if ($function == "getBalance.aspx") {
            $log = 'playboy_api_balance_records';
        }


        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                $function == 'markbyjson.aspx' ? 'body' : 'query' => $function == 'markbyjson.aspx' ? json_encode($this->make_params($function)) : $this->make_params($function),
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

        if ($response['errCode'] !== "0") {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        if ($response['errCode'] == "998" || $response['errCode'] == "70") {
            if ($response['errCode'] == "998") {
                Helpers::sendNotification_admin("Wbet GSC side no balance, player deposit: " . $this->amount);
            }
            Helpers::sendNotification_admin("Wbet provider side no balance, player deposit: " . $this->amount);
        }


        return [
            'status' => $response['errCode'] == "0",
            'status_message' => $response['errMsg'] ?? "no message",
            'data' => $response
        ];
    }

    public function encypt_to_token($function)
    {
        return strtoupper($this->encypt_string($function));
    }

    public function encypt_string($function)
    {
        if ($function == "createMember.aspx") {
            return md5($this->operatorcode . $this->username . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "checkMemberProductUsername.aspx") {
            return md5($this->operatorcode . $this->providercode . $this->username . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "getBalance.aspx") {
            return md5($this->operatorcode . $this->password . $this->providercode . $this->username . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "makeTransfer.aspx") {
            return md5($this->amount . $this->operatorcode . $this->password . $this->providercode . $this->referenceid . $this->type . $this->username . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "checkTransaction.ashx") {
            return md5($this->operatorcode . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "getGameList.aspx") {
            return md5($this->operatorcode . $this->providercode . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "launchGames.aspx") {
            return md5($this->operatorcode . $this->password . $this->providercode . $this->type . $this->username . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "fetchbykey.aspx") {
            return md5($this->operatorcode . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "markbyjson.aspx") {
            return md5($this->operatorcode . config('api.WBET_SECRET_KEY'));
        }
        if ($function == "checkAgentCredit.aspx") {
            return md5($this->operatorcode . config('api.WBET_SECRET_KEY'));
        }
    }

    function hmac_sha1($str, $key)
    {
        $signature = "";
        if (function_exists('hash_hmac')) {
            $signature = base64_encode(hash_hmac("sha1", $str, $key, true));
        } else {
            $blocksize = 64;
            $hashfunc = 'sha1';
            if (strlen($key) > $blocksize) {
                $key = pack('H*', $hashfunc($key));
            }
            $key = str_pad($key, $blocksize, chr(0x00));
            $ipad = str_repeat(chr(0x36), $blocksize);
            $opad = str_repeat(chr(0x5c), $blocksize);
            $hmac = pack(
                'H*',
                $hashfunc(
                    ($key ^ $opad) . pack(
                        'H*',
                        $hashfunc(
                            ($key ^ $ipad) . $str
                        )
                    )
                )
            );
            $signature = base64_encode($hmac);
        }
        return $signature;
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "en";
        }
        if (app()->getLocale() == "cn") {
            return "zh";
        }
        return "en";
    }
}
