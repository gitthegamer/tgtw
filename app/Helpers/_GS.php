<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class _GS
{
    const API_URL = "http://gsmd.336688bet.com/";
    const LOG_URL = "http://gslog.336699bet.com/";

    protected $operatorcode;
    protected $providercode;
    protected $username;
    protected $password;
    protected $referenceid;
    protected $type;
    protected $amount;
    protected $signature;
    protected $gameid;
    protected $lang;
    protected $html5;
    protected $opassword;
    protected $ticket;
    protected $secretkey;
    protected $versionkey;
    protected $reformatJson;
    protected $from;
    protected $to;
    protected $keyOrdate;
    protected $page;
    protected $blimit;

    const TYPE_DEPOSIT = "0";
    const TYPE_WITHDRAW = "1";

    const ERROR_ARRAYS = [
        '0' => 'SUCCESS 请求成功',
        '61' => 'CURRENCY_NOT_SUPPORT 货币不兼容',
        '70' => 'INSUFFICIENT_KIOSK_BALANCE 集成系统余额不足',
        '71' => 'INVALID_REFERENCE_ID 单据号不正确',
        '72' => 'INSUFFICIENT_BALANCE 余额不足',
        '73' => 'INVALID_TRANSFER_AMOUNT 转账金额不正确',
        '81' => 'MEMBER_NOT_FOUND 会员账号不存在',
        '82' => 'MEMBER_EXISTED 会员账号已存在',
        '83' => 'OPERATOR_EXISTED 代理号已存在',
        '90' => 'INVALID_PARAMETER 请求参数不正确',
        '91' => 'INVALID_OPERATOR 代理号不正确',
        '92' => 'INVALID_PROVIDERCODE 供应商代号不正确',
        '93' => 'INVALID_PARAMETER_TYPE 请求参数类型不正确',
        '94' => 'INVALID_PARAMETER_USERNAME 账号不正确',
        '95' => 'INVALID_PARAMETER_PASSWORD 密码不正确',
        '96' => 'INVALID_PARAMETER_OPASSWORD 旧密码不正确',
        '97' => 'INVALID_PARAMETER_EMPTY_DOMAINNAME 请求链接/域名不正确',
        '98' => 'INVALID_USERNAME_OR_PASSWORD 账号/密码错误',
        '-98' => 'INVALID_USERNAME_OR_PASSWORD 账号/密码错误',
        '99' => 'INVALID_SIGNATURE 加密错误',
        '992' => 'INVALID_PARAMETER_PRODUCT_NOT_SUPPORTED_GAMETYPE 平台不兼容请求的游戏类型',
        '991' => 'OPERATOR_STATUS_INACTIVE 代理号已冻结',
        '994' => 'ACCESS_PROHIBITED 接口访问被禁止',
        '995' => 'PRODUCT_NOT_ACTIVATED 平台未开通',
        '996' => 'PRODUCT_NOT_AVAILABLE 平台不支持',
        '998' => 'PLEASE_CONTACT_CSD 请联系客服',
        '999' => 'UNDER_MAINTENENCE 系统维护中',
        '9999' => 'UNKNOWN_ERROR 未知错误',
        '-996' => "UNKNOWN ERROR",
        '-997' => 'SYS_EXCEPTION, Please contact CS. 系统错误，请联络客服。',
        '-998' => 'API_KIOSK_INSUFFICIENT_BALANCE 集成系统接口余额不足',
        '-999' => 'API_ERROR 接口错误',
        '600' => 'pre-check stage FAILED, deposit/withdraw transaction IGNORED 前期检验失败。 存款/取款 操作已被无视',
        '601' => 'DEPO_APIREQ_BLOCKED_FOR_THIS_PRODUCT_TILL_FURTHER_NOTICE 此产品的存款 功能暂时停用维修',
        '602' => 'WITH_APIREQ_BLOCKED_FOR_THIS_PRODUCT_TILL_FURTHER_NOTICE 此产品的取款 功能暂时停用维修',
        '603' => 'Server is going to proceed online maintenance for 10 minutes.',
        '500' => "UNKNOWN ERROR",
        'ERROR' => 'UNDEFINED ERROR',
    ];

    public function explode_tickets(array $tickets)
    {
        return implode(',', $tickets);
    }

    public function create_param($function, $params)
    {
        $this->lang = $this->getLocale();
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->signature = $this->encypt_to_token($function);
    }

    public function make_params($function)
    {
        if ($function == "createMember.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'username' => $this->username,
                'signature' => $this->signature,
            ];
        }
        if ($function == "getBalance.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'providercode' => $this->providercode,
                'username' => $this->username,
                'password' => $this->password,
                'signature' => $this->signature,
            ];
        }
        if ($function == "makeTransfer.aspx") {
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
        }
        if ($function == "launchGames.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'providercode' => $this->providercode,
                'username' => $this->username,
                'password' => $this->password,
                'type' => $this->type,
                'gameid' => $this->gameid,
                'lang' => $this->lang,
                'html5' => $this->html5,
                'blimit' => $this->blimit,
                'signature' => $this->signature,
            ];
        }
        if ($function == "changePassword.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'providercode' => $this->providercode,
                'username' => $this->username,
                'password' => $this->password,
                'opassword' => $this->opassword,
                'signature' => $this->signature,
            ];
        }
        if ($function == "checkTransaction.ashx") {
            return [
                'operatorcode' => $this->operatorcode,
                'referenceid' => $this->referenceid,
                'signature' => $this->signature,
            ];
        }
        if ($function == "checkAgentCredit.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'signature' => $this->signature,
            ];
        }
        if ($function == "checkMemberProductUsername.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'providercode' => $this->providercode,
                'username' => $this->username,
                'signature' => $this->signature,
            ];
        }
        if ($function == "fetchbykey.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'versionkey' => $this->versionkey,
                'signature' => $this->signature,
            ];
        }
        if ($function == "mark.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'ticket' => $this->ticket,
                'signature' => $this->signature,
            ];
        }
        if ($function == "markbyjson.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'ticket' => $this->ticket,
                'signature' => $this->signature,
            ];
        }
        if ($function == "getGameList.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'providercode' => $this->providercode,
                // 'Lang' => $this->lang,
                // 'html5' => $this->html5,
                'reformatJson' => $this->reformatJson,
                'signature' => $this->signature,
            ];
        }

        if ($function == "repullBettingHistoryApiClient.ashx") {
            return [
                'providercode' => $this->providercode,
                'type' => $this->type,
                'from' => $this->from,
                'to' => $this->to,
                'versionkey' => $this->versionkey,
                'keyOrdate' => $this->keyOrdate,
                'page' => $this->page,
                'operatorcode' => $this->operatorcode,
                'signature' => $this->signature,
            ];
        }
        if ($function == "checkAgentCredit.aspx") {
            return [
                'operatorcode' => $this->operatorcode,
                'signature' => $this->signature,
            ];
        }
    }

    public function encypt_to_token($function)
    {
        return strtoupper(md5($this->encypt_string($function)));
    }

    public function encypt_string($function)
    {
        if ($function == "createMember.aspx") {
            return $this->operatorcode . $this->username . $this->secretkey;
        }
        if ($function == "getBalance.aspx") {
            return $this->operatorcode . $this->password . $this->providercode . $this->username . $this->secretkey;
        }
        if ($function == "makeTransfer.aspx") {
            return $this->amount . $this->operatorcode . $this->password . $this->providercode . $this->referenceid . $this->type . $this->username . $this->secretkey;
        }
        if ($function == "launchGames.aspx") {
            return $this->operatorcode . $this->password . $this->providercode . $this->type . $this->username . $this->secretkey;
        }
        if ($function == "changePassword.aspx") {
            return $this->opassword . $this->operatorcode . $this->password . $this->providercode . $this->username . $this->secretkey;
        }
        if ($function == "checkTransaction.ashx") {
            return $this->operatorcode . $this->secretkey;
        }
        if ($function == "checkAgentCredit.aspx") {
            return $this->operatorcode . $this->secretkey;
        }
        if ($function == "checkMemberProductUsername.aspx") {
            return $this->operatorcode . $this->providercode . $this->username . $this->secretkey;
        }
        if ($function == "fetchbykey.aspx") {
            return $this->operatorcode . $this->secretkey;
        }
        if ($function == "mark.aspx") {
            return $this->operatorcode . $this->secretkey;
        }
        if ($function == "markbyjson.aspx") {
            return $this->operatorcode . $this->secretkey;
        }
        if ($function == "getGameList.aspx") {
            return $this->operatorcode . $this->providercode . $this->secretkey;
        }
        if ($function == "repullBettingHistoryApiClient.ashx") {
            return $this->providercode . $this->operatorcode . $this->keyOrdate . $this->secretkey;
        }
        if ($function == "checkAgentCredit.aspx") {
            return $this->operatorcode . $this->secretkey;
        }
    }

    public function get_url($function)
    {
        if ($function == "fetchbykey.aspx" || $function == "mark.aspx" || $function == "markbyjson.aspx" || $function == "repullBettingHistoryApiClient.ashx") {
            return SELF::LOG_URL . $function;
        } else {
            return SELF::API_URL . $function;
        }
    }

    public static function init($function, $params)
    {
        $controller = new _GSController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();

        $log = 'gamingsoft_api_records';
        if ($function == "repullBettingHistoryApiClient.ashx" || $function == "fetchbykey.aspx" || $function == "json.aspx" || $function == "markbyjson.aspx") {
            $log = 'gamingsoft_api_ticket_records';
        }
        if ($function == "makeTransfer.aspx" || $function == "checkTransaction.ashx") {
            $log = 'gamingsoft_api_transfer_records';
        }
        if ($function == "getBalance.aspx") {
            $log = 'gamingsoft_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats$stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'query' => $this->make_params($function),
            ]);

            $response = @json_decode($response->getBody(), true);
            Log::channel($log)->debug("$time Response: " . json_encode($response));
        } catch (Exception $e) {
            Log::channel($log)->debug("$time " . (SELF::ERROR_ARRAYS['ERROR'] ?? "Unknown ERROR") . "$e");
            return [
                'status' => false,
                'status_message' => SELF::ERROR_ARRAYS['ERROR'] ?? "Unknown ERROR",
                'status_server_message' => "Unknown status code ($e)",
                'balance' => null,
                'gameUrl' => null,
                'data' => null,
                'result' => null,
                'gamelist' => null,
            ];
        }

        if (!$response) {
            Log::channel($log)->debug("$time Status: Unknown");
            return [
                'status' => false,
                'status_message' => SELF::ERROR_ARRAYS['ERROR'] ?? "Unknown ERROR",
                'status_server_message' => "Connection error",
                'balance' => null,
                'gameUrl' => null,
                'data' => null,
                'result' => null,
                'gamelist' => null,
            ];
        }

        if (isset(SELF::ERROR_ARRAYS[$response['errCode']])) {
            Log::channel($log)->debug("$time Status: " . SELF::ERROR_ARRAYS[$response['errCode']]);
        } else {
            Log::channel($log)->debug("$time Status: Unknown");
        }

        return [
            'status' => ($response['errCode'] == '0') ? true : false,
            'status_code' => $response['errCode'],
            'status_message' => SELF::ERROR_ARRAYS[$response['errCode']] ?? "Unknown Error",
            'status_server_message' => $response['errMsg'],
            'balance' => ($response['balance']) ?? null,
            'gameUrl' => ($response['gameUrl']) ?? null,
            'data' => ($response['data']) ?? null,
            'result' => ($response['result']) ?? null,
            'gamelist' => ($response['gamelist']) ?? null,
            'record' => ($response['record']) ?? null,
            'lastversionkey' => ($response['lastversionkey']) ?? null,
        ];
    }

    public function getLocale()
    {
        if(request()->lang == "en"){
            return "en-US";
        }
        if(request()->lang == "ms-MY"){
            return "ms-MY";
        }
        if(request()->lang == "zh-CN"){
            return "zh-CN";
        }
        return "en-US";
    }
}
