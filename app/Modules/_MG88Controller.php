<?php

namespace App\Modules;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use App\Models\Log as ModelsLog;

class _MG88Controller
{

    protected $agentLoginId;
    protected $secretCode;
    protected $sn;
    protected $loginId;
    protected $amount;
    protected $bizId;
    protected $startTime;
    protected $endTime;
    protected $pageIndex;

    public static function init($function, $params)
    {
        $controller = new _MG88Controller();
        return $controller->request($function, $params);
    }

    public function get_url($function)
    {
        return config('api.MEGA_LINK') . $function;
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function make_params($function)
    {
        $random = Str::uuid();

        if ($function == "open.mega.user.create") {
            return [
                "random" => $random,
                "digest" => md5($random . config('api.MEGA_SN') . config('api.MEGA_SECRET_CODE')),
                "sn" => config('api.MEGA_SN'),
                "nickname" => null,
                "agentLoginId" => $this->agentLoginId,
            ];
        }
        if ($function == "open.mega.balance.get") {
            return [
                "random" => $random,
                "digest" => md5($random . config('api.MEGA_SN') . $this->loginId . config('api.MEGA_SECRET_CODE')),
                "sn" => config('api.MEGA_SN'),
                "loginId" => $this->loginId,
            ];
        }
        if ($function == "open.mega.balance.transfer") {
            return [
                "random" => $random,
                "digest" => md5($random . config('api.MEGA_SN') . $this->loginId . $this->amount . config('api.MEGA_SECRET_CODE')),
                "sn" => config('api.MEGA_SN'),
                "loginId" => $this->loginId,
                "amount" => $this->amount,
                "bizId" => $this->bizId,
                "checkBizId" => 1
            ];
        }
        if ($function == "open.mega.balance.transfer.query") {
            return [
                "random" => $random,
                "digest" => md5($random . config('api.MEGA_SN') . config('api.MEGA_SECRET_CODE')),
                "sn" => config('api.MEGA_SN'),
                "loginId" => $this->loginId,
                "agentLoginId" => $this->agentLoginId,
                "bizId" => $this->bizId,
            ];
        }
        if ($function == "open.mega.game.order.page") {
            return [
                "random" => $random,
                "digest" => md5($random . config('api.MEGA_SN') . $this->loginId . config('api.MEGA_SECRET_CODE')),
                "sn" => config('api.MEGA_SN'),
                "loginId" => $this->loginId,
                "startTime" => $this->startTime,
                "endTime" => $this->endTime,
                "pageIndex" => $this->pageIndex,
                "pageSize" => 1000,
            ];
        }
        if ($function == "open.mega.player.total.report") {
            return [
                "random" => $random,
                "digest" => md5($random . config('api.MEGA_SN') . $this->agentLoginId . config('api.MEGA_SECRET_CODE')),
                "sn" => config('api.MEGA_SN'),
                "agentLoginId" => $this->agentLoginId,
                "startTime" => $this->startTime,
                "endTime" => $this->endTime,
            ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_MG,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $log = 'mega888_api_records';
        if ($function == "open.mega.game.order.page") {
            $log = 'mega888_api_ticket_records';
        }
        if ($function == "open.mega.balance.transfer.query" || $function == "open.mega.balance.transfer") {
            $log = 'mega888_api_transfer_records';
        }
        if ($function == "open.mega.balance.get") {
            $log = 'mega888_api_balance_records';
        }

        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);

        $body = [
            "id" => Str::uuid(),
            "method" => $function,
            "params" => $this->make_params($function),
            "jsonrpc" => "2.0"
        ];

        Log::channel($log)->debug("$time Params : " . json_encode($body));
        try {
            $ch = curl_init($this->get_url($function));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'json=' . json_encode($body));
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
            $res = curl_exec($ch);
            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                Log::channel($log)->debug("$time Error: " . $error_msg);

                $logForDB['status'] = ModelsLog::STATUS_ERROR;
                $logForDB['trace'] = "$time Error: " . $error_msg;
                ModelsLog::addLog($logForDB);
                return [
                    'status' => false,
                    'status_message' => "Connection Error",
                    'data' => [],
                ];
            }
            curl_close($ch);
            $response = @json_decode($res, true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            Log::channel($log)->debug("$time Response: " . @json_encode($response));
        } catch (\Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => [],
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
                'data' => [],
            ];
        }

        $message = null;
        if ($response['error'] !== null) {
            $message = $response['error']['message'];
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['message'] = $message;
            Log::channel($log)->debug("$time Status: " . $response['error']['message']);
        } else {
            $message = "OK";
            Log::channel($log)->debug("$time Status: OK");
        }

        return [
            'status' => $response['error'] === null ? true : false,
            'status_message' => '',
            'data' => $response['error'] === null ? $response['result'] : [],
        ];
    }

    public static function callback()
    {
        Log::channel('mega888_api_login_records')->debug("callback start");
        $request = json_decode(str_replace("json=", "", request()->getContent()));
        Log::channel('mega888_api_login_records')->debug("request : " . request()->getContent());
        $result = false;
        try {
            if ($request->method == "open.operator.user.login") {
                $digest = strtoupper(md5($request->params->random . config('api.MEGA_SN') . $request->params->loginId . config('api.MEGA_SECRET_CODE')));
                if ($request->params->digest === $digest) {
                    $member = \App\Models\MemberAccount::where('username', $request->params->loginId)->where('password', $request->params->password)->first();
                    if ($member) {
                        $result = [
                            "success" => "1",
                            "sessionId" => \Illuminate\Support\Str::uuid(),
                            "msg" => "Login Success",
                        ];
                    } else {
                        $result = [
                            "success" => "0",
                            "sessionId" => \Illuminate\Support\Str::uuid(),
                            "msg" => "Login Failed",
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            Log::channel('mega888_api_login_records')->debug("exception : " . $e);
        }

        if ($result !== false) {
            $response = array("id" => $request->id, 'result' => $result, 'error' => NULL, 'jsonrpc' => "2.0");
        } else {
            $response = array("id" => $request->id, 'result' => NULL, 'error' => 'unknown method or incorrect parameters', 'jsonrpc' => "2.0");
        }
        Log::channel('mega888_api_login_records')->debug("response : " . json_encode($response));
        Log::channel('mega888_api_login_records')->debug("callback end");

        return json_encode($response);
    }
}
