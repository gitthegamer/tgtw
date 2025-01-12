<?php

namespace App\Modules;

use App\Models\ProviderLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class _VivoGamingController
{
    const BASE_LINK = "http://ecsystem.com/";

    const ERRORS = [
        2 => "token was not found",
        3 => "parameters mismatch",
        5 => "integrator URL error",
        29 => "database error",
        55 => "integrator url does not have mapping",
        56 => "Integrator server error",
        101 => "Invalid Token",
        102 => "Session Expired",
        103 => "Invalid Status Table Reading",
        104 => "Table Status Does Not Exist",
        105 => "Late Bets Rejection",
        106 => "Table is in closing procedure",
        107 => "Table is closed",
        108 => "No Proper Bets Reported",
        109 => "Insufficient Funds at STP System, newbalance=[XXXXX]",
        110 => "Player Record is Locked for too long",
        111 => "Player Balance Update Error",
        137 => "Integrator Player Operator Has Been Changed",
        138 => "Integration error, unable to build integrator player in host system",
        141 => "Internal DB Error, Could not locate built player id",
        142 => "Internal DB Error, Fail to insert Integrator to Mapping Table",
        155 => "Invalid Table ID",
        175 => "Player Record is Locked for too long",
        200 => "Integration Bet Error, Integrator Description=[Description]",
        212 => "Insufficient Funds at Integrator System,newbalance=XXXX",
        222 => "Permission denied",
        555 => "Integrator Has Past Fault that needs attention; please contact your 
        supplier – (Fail Safety System)",
        300 => "Insufficient funds",
        301 => "Operation failed",
        302 => "Unknown transaction id for Status API",
        310 => "Unknown user ID",
        399 => "Internal Error",
        400 => "Invalid token",
        500 => "Invalid hash",
        812 => "Betting limit reached",
        299 => "Win of unrecognized bet",
    ];

    protected $token;
    protected $hash;
    protected $userId;
    protected $Amount;
    protected $TransactionID;
    protected $TrnType;
    protected $TrnDescription;
    protected $roundId;
    protected $gameId;
    protected $History;
    protected $isRoundFinished;
    protected $sessionId;
    protected $casinoTransactionId;

    public static function init($function, $params)
    {
        $controller = new _VpowerController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
        $this->hash = $this->encypt_to_token($function);

    }

    public function get_url($function)
    {
        return SELF::BASE_LINK;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "authenticate.do":
                return [
                    'token=' => $this->token,
                    'hash' => $this->hash,
                ];
            case "getbalance.do";
                return [
                    'userId' => $this->userId,
                    'hash' => $this->hash,
                ];
            case "ChangeBalance.aspx":
                return [
                    'userId' => $this->userId,
                    'Amount' => $this->Amount,
                    'TransactionID' => $this->TransactionID,
                    'TrnType' => $this->TrnType,
                    'TrnDescription' => $this->TrnDescription,
                    "roundId" => $this->roundId,
                    'gameId' => $this->gameId,
                    'History' => $this->History,
                    'isRoundFinished' => $this->isRoundFinished,
                    'hash' => $this->hash,
                    'sessionId' => $this->sessionId
                ];
            case "requeststatus.do":
                return [
                    'userId' => $this->userId,
                    'casinoTransactionId' => $this->casinoTransactionId,
                ];
            
          
        }
    }

    public function request($function, $params)
    {
        $time = time();

        $log = 'vivogaming_api_records';
        if ($function == "glog") {
            $log = 'vivogaming_api_ticket_records';
        }
        if ($function == "ChangeBalance.aspx") {
            $log = 'vivogaming_api_transfer_records';
        }
        if ($function == "getbalance.do") {
            $log = 'vivogaming_api_balance_records';
        }
        

        Log::channel($log)->debug("$time Function : " . $function);
        $productName = 'Vivo Gaming';

        $this->create_param($function, $params);

        Log::channel($log)->debug("$time Params : " . json_encode($this->make_params($function)));
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {
                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
              

                },
                'form_params' => $this->make_params($function),
            ]);

            $response = @json_decode($response->getBody(), true);
            Log::channel($log)->debug("$time Response: " . json_encode($response));
          
        } catch (Exception $e) {
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
   
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }

        if (!$response) {
            Log::channel($log)->debug("$time Status: Unknown");
     
            return [
                'status' => false,
                'status_message' => "Connection Error",
                'data' => null,
            ];
        }

        return [
            'status' => ($response['result'] == 'OK' ) ? true : false,
            'status_message' => $response['result'] == 'OK'  ? "success" : "no message",
            'data' => $response
        ];
    }

    public function encypt_to_token($function)
    {
        return md5($this->encypt_string($function));
    }
    public function encypt_string($function)
    {
        if ($function == "authenticate.do") {
            return "token=".$this->token;
        }
        if ($function == "getbalance.do") {
            return "userId=".$this->userId;
        }
        if ($function == "requeststatus.do"){
            return "userId=".$this->userId."&casinoTransactionId=".$this->casinoTransactionId;
        }
        
    }

      public static function getLocale()
      {
          if (request()->lang == "en") {
              return "en";
          }
          if (request()->lang == "cn") {
              return "zh";
          }
          return "en";
      }

}
