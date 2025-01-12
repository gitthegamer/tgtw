<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Illuminate\Support\Str;

class _BGController
{
    protected $random;
    protected $sn;
    protected $loginId;
    protected $agentId;
    protected $agentLoginId;
    protected $amount;
    protected $bizId;
    protected $sign;
    protected $password;
    protected $username;
    protected $secret_key;
    protected $startTime;
    protected $endTime;
    protected $pageIndex;
    protected $time;
    protected $value;
    protected $reqTime;
    protected $orderId;

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->sign = $this->encypt_to_token($function);
    }

    public function make_params($function)
    {
        if ($function == "open.agent.create") {
            return [
                'random' => $this->random,
                'sign' => $this->sign,
                'sn' => $this->sn,
                'loginId' => $this->loginId,
                'password' => $this->password,
            ];
        }
        if ($function == "open.user.create") {
            return [
                'random' => $this->random,
                'digest' => $this->sign,
                'sn' => $this->sn,
                'loginId' => $this->loginId,
                'agentLoginId' => $this->agentLoginId,
            ];
        }
        if ($function == "open.balance.get") {
            return [
                'random' => $this->random,
                'digest' => $this->sign,
                'sn' => $this->sn,
                'loginId' => $this->loginId,
            ];
        }
        if ($function == "open.balance.transfer") {
            return [
                'random' => $this->random,
                'digest' => $this->sign,
                'sn' => $this->sn,
                'loginId' => $this->loginId,
                'amount' => $this->amount,
                'bizId' => $this->bizId,
            ];
        }
        if ($function == "open.video.game.url") {
            return [
                'random' => $this->random,
                'digest' => $this->sign,
                'sn' => $this->sn,
                'loginId' => $this->loginId,
            ];
        }
        if ($function == "open.balance.transfer.query") {
            return [
                'random' => $this->random,
                'sign' => $this->sign,
                'sn' => $this->sn,
                'loginId' => $this->loginId,
                'bizId' => $this->bizId,
            ];
        }
        if ($function == "open.order.agent.query") {
            return [
                'random' => $this->random,
                'digest' => $this->sign,
                'sn' => $this->sn,
                'startTime' => $this->startTime,
                'endTime' => $this->endTime,
                'pageIndex' => $this->pageIndex,
                'pageSize' => 1000,
                'agentLoginId' => $this->agentLoginId,
            ];
        }
        if ($function == "open.game.limitations.set") {
            return [
                'random' => $this->random,
                'sign' => $this->sign,
                'sn' => $this->sn,
                'time' => $this->time,
                'loginId' => $this->loginId,
                'value' => $this->value,
            ];
        }
        if ($function == "open.game.limitations.list") {
            return [
                'random' => $this->random,
                'sign' => $this->sign,
                'sn' => $this->sn,
                'time' => $this->time,
            ];
        }
        if ($function == "open.sn.video.order.detail") {
            return [
                'random' => $this->random,
                'sign' => $this->sign,
                'reqTime' => $this->reqTime,
                'sn' => $this->sn,
                'orderId' => $this->orderId,
            ];
        }
    }

    public function encypt_to_token($function)
    {
        return strtolower(md5($this->encypt_string($function)));
    }

    public function encypt_string($function)
    {
        if ($function == "open.agent.create") {
            return $this->random . $this->sn . $this->loginId . $this->secret_key;
        }
        if ($function == "open.user.create") {
            return $this->random . $this->sn . base64_encode(hash('sha1', $this->password, true));
        }
        if ($function == "open.balance.get") {
            return $this->random . $this->sn . $this->loginId . base64_encode(hash('sha1', $this->password, true));
        }
        if ($function == "open.balance.transfer") {
            return $this->random . $this->sn . $this->loginId . $this->amount . base64_encode(hash('sha1', $this->password, true));
        }
        if ($function == "open.video.game.url") {
            return $this->random . $this->sn . $this->loginId . base64_encode(hash('sha1', $this->password, true));
        }
        if ($function == "open.balance.transfer.query") {
            return $this->random . $this->sn . $this->secret_key;
        }
        if ($function == "open.order.agent.query") {
            return $this->random . $this->sn . base64_encode(hash('sha1', $this->password, true));
        }
        if ($function == "open.game.limitations.set") {
            return $this->random . $this->sn . $this->time . $this->secret_key;
        }
        if ($function == "open.game.limitations.list") {
            return $this->random . $this->sn . $this->time . $this->secret_key;
        }
        if ($function == "open.sn.video.order.detail") {
            return $this->random . $this->sn . $this->orderId . $this->reqTime . $this->secret_key;
        }
    }

    public function get_url($function)
    {
        return config('api.BG_LINK') . $function;
    }

    public static function init($function, $params)
    {
        $controller = new _BGController();
        return $controller->request($function, $params);
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_BG,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];

        $this->create_param($function, $params);

        $body = [
            "id" => Str::uuid(),
            "method" => $function,
            "params" => $this->make_params($function),
            "jsonrpc" => "2.0"
        ];

        try {
            $ch = curl_init($this->get_url($function));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'json=' . json_encode($body));
            $res = curl_exec($ch);
            if (curl_errno($ch)) {
                return false;
            }
            curl_close($ch);
            $response = @json_decode($res, true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
        } catch (\Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);

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
            $logForDB['trace'] = $message;

        } else {
            $message = "OK";
        }

        return [
            'status' => $response['error'] === null ? true : false,
            'status_message' => $message,
            'data' => $response['error'] === null ? $response['result'] : [],
        ];
    }
}
