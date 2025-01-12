<?php

namespace App\Modules;

use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;

class _DreamController
{
    const API_URL = "http://api.dg99web.com/";

    protected $sign;
    protected $random;
    protected $data;
    protected $member;
    protected $agentName;
    protected $api_key;
    protected $lang;
    protected $domains;

    const ERRORS = [
        "0" => "Operation Successful",
        "1" => "Parameter Error",
        "2" => "Token Verification Failed",
        "3" => "Command Not Find",
        "4" => "Illegal Operation",
        "10" => "Date format error",
        "11" => "Data format error",
        "97" => "Permission denied",
        "98" => "Operation failed",
        "99" => "Unknown Error",
        "100" => "Account is locked",
        "101" => "Account format error",
        "102" => "Account does not exist",
        "103" => "This account is taken",
        "104" => "Password format error",
        "105" => "Password wrong",
        "106" => "New & Old Password is the same",
        "107" => "Member account unavailable",
        "108" => "Login Error",
        "109" => "Signup Error",
        "110" => "This account has been signed in",
        "111" => "This account has been signed out",
        "112" => "This account is not signed in",
        "113" => "The Agent account inputted is not an Agent account",
        "114" => "Member not found",
        "116" => "Account occupied",
        "117" => "Can not find branch of member",
        "118" => "Can not find the specified Agent",
        "119" => "Insufficent funds during Agent withdrawal",
        "120" => "Insufficient balance",
        "121" => "Profit limit must be greater than or equal to 0",
        "150" => "Ran out of free demo accounts",
        "300" => "system maintenance",
        "301" => "Agent account not found",
        "320" => "Wrong API key",
        "321" => "Limit Group Not Found",
        "322" => "Currency Name Not Found",
        "323" => "Use serial numbers for Transfer",
        "324" => "Transfer failed",
        "325" => "Agent Status Unavailable",
        "326" => "Members Agent No video group",
        "400" => "Client IP Restricted",
        "401" => "Network latency",
        "402" => "The connection is closed",
        "403" => "Clients limited sources",
        "404" => "Resource requested does not exist",
        "405" => "Too frequent requests",
        "406" => "Request timed out",
        "407" => "Can not find game address",
        "500" => "Null pointer exception",
        "501" => "System Error",
        "502" => "The system is busy",
        "503" => "Data operation error",
    ];

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->sign = $this->encypt_to_token($function);
    }

    public function make_params($function)
    {
        if ($function == "user/signup/") {
            return [
                'token' => $this->sign,
                'random' => $this->random,
                'data' => $this->data,
                'member' => $this->member,
            ];
        }
        if ($function == "user/getBalance/") {
            return [
                'token' => $this->sign,
                'random' => $this->random,
                'member' => $this->member,
            ];
        }
        if ($function == "account/transfer/") {
            return [
                'token' => $this->sign,
                'random' => $this->random,
                'data' => $this->data,
                'member' => $this->member,
            ];
        }
        if ($function == "account/checkTransfer/") {
            return [
                'token' => $this->sign,
                'random' => $this->random,
                'data' => $this->data,
            ];
        }
        if ($function == "user/login/") {
            return [
                'token' => $this->sign,
                'random' => $this->random,
                'lang' => $this->lang,
                'domains' => $this->domains,
                'member' => $this->member,
            ];
        }
        if ($function == "game/getReport/") {
            return [
                'token' => $this->sign,
                'random' => $this->random,
            ];
        }
    }

    public function encypt_to_token($function)
    {
        return strtolower(md5($this->encypt_string($function)));
    }

    public function encypt_string($function)
    {
        if ($function == "user/signup/") {
            return $this->agentName . $this->api_key . $this->random;
        }
        if ($function == "user/getBalance/") {
            return $this->agentName . $this->api_key . $this->random;
        }
        if ($function == "account/transfer/") {
            return $this->agentName . $this->api_key . $this->random;
        }
        if ($function == "account/checkTransfer/") {
            return $this->agentName . $this->api_key . $this->random;
        }
        if ($function == "user/login/") {
            return $this->agentName . $this->api_key . $this->random;
        }
        if ($function == "game/getReport/") {
            return $this->agentName . $this->api_key . $this->random;
        }
    }

    public function get_url($function)
    {
        return SELF::API_URL . $function . $this->agentName;
    }

    public static function generateLogin($game_url, $token, $lang)
    {
        return "$game_url$token&language=$lang";
    }

    public static function init($function, $params)
    {
        $controller = new _DreamController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_DG,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);

        $params = json_encode($this->make_params($function));

        $ch = curl_init($this->get_url($function));

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params),
        ]);

        $res = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }
        curl_close($ch);
        $response = @json_decode($res, true);
        $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
        $logForDB['trace'] = json_encode($response);

        if (!$response) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);
            return [
                'status' => false,
                'status_message' => "Connection Error",
                'data' => [],
            ];
        }

        $message = null;
        if ($response['codeId'] !== 0) {
            $message = SELF::ERRORS[$response['codeId']];
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($message);
        } else {
            $message = "OK";
        }

        return [
            'status' => ($response['codeId'] == "0") ? true : false,
            'status_message' => $message,
            'data' => $response
        ];
    }
}
