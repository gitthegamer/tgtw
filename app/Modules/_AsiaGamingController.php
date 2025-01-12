<?php

namespace App\Modules;

use App\Helpers\DesEncrypt;
use App\Models\Log as ModelsLog;

class _AsiaGamingController
{
    protected $key;
    protected $encryptedParams;
    protected $params;
    protected $cagent;
    protected $loginname;
    protected $method;
    protected $actype;
    protected $oddtype;
    protected $password;
    protected $cur;
    protected $sid;
    protected $lang;
    protected $gameType;
    protected $billno;
    protected $type;
    protected $credit;
    protected $startdate;
    protected $enddate;
    protected $page;
    protected $order;
    protected $flag;

    public static function init($function, $params)
    {
        $controller = new _AsiaGamingController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->encryptedParams = $this->encypt_to_des($function);

        if ($function === "betlog") {
            $this->key = strtolower(md5($this->cagent . $this->startdate . $this->enddate . $this->page . config('api.AG_PLAIN_CODE')));
        } else {
            $this->key = strtolower(md5($this->encryptedParams . config('api.AG_SECRET_KEY_MD5')));
        }
    }

    public function make_params($function)
    {
        if ($function == "lg") {
            return [
                'params' => $this->encryptedParams,
                'key' => $this->key,
            ];
        }
        if ($function == "gb") {
            return [
                'params' => $this->encryptedParams,
                'key' => $this->key,
            ];
        }
        if ($function == "tc") {
            return [
                'params' => $this->encryptedParams,
                'key' => $this->key,
            ];
        }
        if ($function == "tcc") {
            return [
                'params' => $this->encryptedParams,
                'key' => $this->key,
            ];
        }
        if ($function == "qos") {
            return [
                'params' => $this->encryptedParams,
                'key' => $this->key,
            ];
        }
        if ($function == "login") {
            return [
                'params' => $this->encryptedParams,
                'key' => $this->key,
            ];
        }
        if ($function == "betlog") {
            return [
                'cagent' => $this->cagent,
                'startdate' => $this->startdate,
                'enddate' => $this->enddate,
                'page' => $this->page,
                'key' => $this->key,
            ];
        }
    }

    public function get_url($function)
    {
        if ($function == "login") {
            return config('api.AG_LOGIN_LINK') . "forwardGame.do";
        } else if ($function == "betlog") {
            return config('api.AG_REPORT_LINK') . "getorders.xml";
        } else {
            return config('api.AG_LINK') . "doBusiness.do";
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "GET";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_AG,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];
   

        $this->create_param($function, $params);
        $result = $this->make_params($function);
        $baseUrl = $this->get_url($function);

        if ($function !== "betlog") {
            $query = http_build_query([
                'params' => $this->encryptedParams,
                'key' => $this->key,
            ]);
        } else {
            $query = "cagent=" . $this->cagent
                . "&startdate=" . $this->startdate
                . "&enddate=" . $this->enddate
                . "&page=" . $this->page
                . "&key=" . $this->key;
        }

        $url = $baseUrl . '?' . $query;

        if ($function === "login") {
            return [
                'status' =>  true,
                'status_message' => "",
                'data' => $url
            ];
        }

        try {
            $client = new \GuzzleHttp\Client();
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'WEB_LIB_GI_' . config('api.AG_CAGENT'),
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($function) {
                },
            ];
    
            if ($function !== "betlog") {
                $options['json'] = $result;
            }
    
            if ($function === "betlog") {
                $method = "GET";
                $logForDB['method'] = $method;
                $response = $client->get($url, $options);
            } else {
                $method = "POST";
                $logForDB['method'] = $method;
                $response = $client->post($url, $options);
            }

            $response = @json_decode(json_encode(simplexml_load_string($response->getBody())), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            return [
                'status' => false,
                'status_message' => $e->getMessage(),
                'data' => null,
            ];
        }

        if (!$response) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);

            return [
                'status' => false,
                'status_message' => "",
                'data' => null,
            ];
        }

     
        return [
            'status' =>  true,
            'status_message' => "",
            'data' => $response
        ];
    }

    public function encypt_string($function)
    {
        if ($function == "lg") {
            $data = "cagent=" . $this->cagent . '/\\\\\\\\/' .
                "loginname=" . $this->loginname . '/\\\\\\\\/' .
                "method=" . $this->method . '/\\\\\\\\/' .
                "actype=" . $this->actype . '/\\\\\\\\/' .
                "password=" . $this->password . '/\\\\\\\\/' .
                "oddtype=" . $this->oddtype . '/\\\\\\\\/' .
                "cur=" . $this->cur;
            return $data;
        }
        if ($function == "gb") {
            $data = "cagent=" . $this->cagent . '/\\\\\\\\/' .
                "loginname=" . $this->loginname . '/\\\\\\\\/' .
                "method=" . $this->method . '/\\\\\\\\/' .
                "actype=" . $this->actype . '/\\\\\\\\/' .
                "password=" . $this->password . '/\\\\\\\\/' .
                "cur=" . $this->cur;
            return $data;
        }
        if ($function == "tc") {
            $data = "cagent=" . $this->cagent . '/\\\\\\\\/' .
                "method=" . $this->method . '/\\\\\\\\/' .
                "loginname=" . $this->loginname . '/\\\\\\\\/' .
                "billno=" . $this->billno . '/\\\\\\\\/' .
                "type=" . $this->type . '/\\\\\\\\/' .
                "credit=" . $this->credit . '/\\\\\\\\/' .
                "actype=" . $this->actype . '/\\\\\\\\/' .
                "password=" . $this->password . '/\\\\\\\\/' .
                "cur=" . $this->cur;
            return $data;
        }
        if ($function == "tcc") {
            $data = "cagent=" . $this->cagent . '/\\\\\\\\/' .
                "loginname=" . $this->loginname . '/\\\\\\\\/' .
                "method=" . $this->method . '/\\\\\\\\/' .
                "billno=" . $this->billno . '/\\\\\\\\/' .
                "type=" . $this->type . '/\\\\\\\\/' .
                "credit=" . $this->credit . '/\\\\\\\\/' .
                "actype=" . $this->actype . '/\\\\\\\\/' .
                "flag=" . $this->flag . '/\\\\\\\\/' .
                "password=" . $this->password . '/\\\\\\\\/' .
                "cur=" . $this->cur;
            return $data;
        }
        if ($function == "qos") {
            $data = "cagent=" . $this->cagent . '/\\\\\\\\/' .
                "billno=" . $this->billno . '/\\\\\\\\/' .
                "method=" . $this->method . '/\\\\\\\\/' .
                "actype=" . $this->actype . '/\\\\\\\\/' .
                "cur=" . $this->cur;
            return $data;
        }
        if ($function == "login") {
            $data = "cagent=" . $this->cagent . '/\\\\\\\\/' .
                "loginname=" . $this->loginname . '/\\\\\\\\/' .
                "actype=" . $this->actype . '/\\\\\\\\/' .
                "password=" . $this->password . '/\\\\\\\\/' .
                "sid=" . $this->sid . '/\\\\\\\\/' .
                "lang=" . $this->lang . '/\\\\\\\\/' .
                "gameType=" . $this->gameType . '/\\\\\\\\/' .
                "oddtype=" . $this->oddtype . '/\\\\\\\\/' .
                "cur=" . $this->cur;
            return $data;
        }
        if ($function == "betlog") {
            //betlog no using des encrypt
            return "";
        }
    }

    public function encypt_to_des($function)
    {
        $key = config('api.AG_SECRET_KEY_DES');
        $dataToEncrypt = $this->encypt_string($function);
        $result = DesEncrypt::encrypt($dataToEncrypt, $key);

        return $result;
    }


    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "3";
        }
        if (request()->lang == "cn") {
            return "1";
        }
        return "1";
    }
}
