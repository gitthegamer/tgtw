<?php

namespace App\Modules;

use Illuminate\Support\Facades\Log;

class _888kingController
{
    const API_URL = "https://api.888-king.com/platform/";
    // const API_URL = "https://api.888-king.com/platform/";
    const ERROR_ARRAYS = [
        "api_getbalance.php" => [
            "100" => "success", ["Balance" => "player current credit"],
            "101" => "Platform error",
            "102" => "Sign error",
            "103" => "Parameter error",
            "104" => "Function Error",
            "301" => "User not found",
        ],
        "api_create_user.php" => [
            "100" => "Create user success",
            "101" => "Platform error",
            "102" => "Sign error",
            "103" => "Parameter error",
            "104" => "Function Error",
            "105" => "Username repeated",
            "106" => "PlatformUID repeated",
        ],
        "api_update.php" => [
            "100" => "Create user success",
            "101" => "Platform error",
            "102" => "Sign error",
            "103" => "Parameter error",
            "205" => "User set score failed",
            "206" => "Agent score not enough",
            "207" => "User score not enough",
        ],
        "api_changepw.php" => [
            "100" => "success",
            "101" => "Platform error",
            "102" => "Sign error",
            "103" => "Parameter error",
            "104" => "Function error",
            "301" => "User not found",
        ],
        "api_kickplayer.php" => [
            "100" => "success",
            "101" => "Platform error",
            "102" => "Sign error",
            "103" => "Parameter error",
            "104" => "Function error",
            "301" => "User not found",
            "302" => "Player is offline",
        ],
        "api_report.php" => [
            "100" => "success", ["Report" => "Report"],
            "101" => "Platform error",
            "102" => "Sign error",
            "103" => "Parameter error",
            "104" => "Function Error",
        ],
    ];

    protected $platformID;
    protected $platformUID;
    protected $username;
    protected $password;
    protected $timestamp;
    protected $function;
    protected $sign;
    protected $phone;
    protected $balance;
    protected $new_password;
    protected $SDate;
    protected $EDate;
    protected $limit;
    protected $page;
    protected $secret;

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->sign = $this->encypt_to_token($function);
    }

    public function make_params($function)
    {
        if ($function == "api_getbalance.php") {
            return [
                'PlatformID' => $this->platformID,
                'PlatformUID' => $this->platformUID,
                'Username' => $this->username,
                'Function' => $this->function,
                'Timestamp' => $this->timestamp,
                'Sign' => $this->sign,
            ];
        }

        if ($function == "api_create_user.php") {
            return [
                'PlatformID' => $this->platformID,
                'PlatformUID' => $this->platformUID,
                'Username' => $this->username,
                'Password' => $this->password,
                'Phone' => $this->phone,
                'Function' => $this->function,
                'Timestamp' => $this->timestamp,
                'Sign' => $this->sign,
            ];
        }

        if ($function == "api_updatebalance.php") {
            return [
                'PlatformID' => $this->platformID,
                'PlatformUID' => $this->platformUID,
                'Username' => $this->username,
                'Balance' => $this->balance,
                'Function' => $this->function,
                'Timestamp' => $this->timestamp,
                'Sign' => $this->sign,
            ];
        }

        if ($function == "api_report.php") {
            return [
                'PlatformID' => $this->platformID,
                'PlatformUID' => $this->platformUID,
                'SDate' => $this->SDate,
                'EDate' => $this->EDate,
                'Limit' => $this->limit,
                'Page' => $this->page,
                'Timestamp' => $this->timestamp,
                'Function' => $this->function,
                'Sign' => $this->sign,
            ];
        }
    }

    public function encypt_to_token($function)
    {
        return strtolower(md5($this->encypt_string($function)));
    }

    public function encypt_string($function)
    {
        if ($function == "api_getbalance.php") {
            return $this->platformID . $this->function . $this->platformUID . $this->username . $this->timestamp . $this->secret;
        }
        if ($function == "api_create_user.php") {
            return $this->platformID . $this->function . $this->platformUID . $this->username . $this->password . $this->phone . $this->timestamp . $this->secret;
        }
        if ($function == "api_updatebalance.php") {
            return $this->platformID . $this->function . $this->platformUID . $this->username . $this->balance . $this->timestamp . $this->secret;
        }
        if ($function == "api_report.php") {
            return $this->platformID . $this->function . $this->platformUID . $this->SDate . $this->EDate . $this->page . $this->limit . $this->timestamp . $this->secret;
        }
    }

    public function get_url($function)
    {
        return SELF::API_URL . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _888kingController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();

        $log = '888king_api_records';
        if ($function == "api_report.php") {
            $log = '888king_api_ticket_records';
        }
        if ($function == "api_updatebalance.php") {
            $log = '888king_api_transfer_records';
        }
        if ($function == "api_getbalance.php") {
            $log = '888king_api_balance_records';
        }

        $this->create_param($function, $params);

        try {
            $ch = curl_init($this->get_url($function));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->make_params($function)));
            $res = curl_exec($ch);
            if (curl_errno($ch)) {
                return false;
            }
            curl_close($ch);
            $response = @json_decode($res, true);
        } catch (\Exception $e) {
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => [],
            ];
        }

        if (!$response) {
            return [
                'status' => false,
                'status_message' => SELF::ERROR_ARRAYS[$function][$response['Code']] ?? "Unknown ERROR",
                'data' => null,
            ];
        }

        if (isset($response['Code']) && $response['Code'] != "100") {
        } else {
        }

        return [
            'status' => ($response['Code'] == "100") ? true : false,
            'status_message' => SELF::ERROR_ARRAYS[$response['Code']] ?? "Unknown Error",
            'data' => $response
        ];
    }
}
