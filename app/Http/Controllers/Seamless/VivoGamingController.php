<?php

namespace App\Http\Controllers\Seamless;

use App\Http\Controllers\Controller;
use App\Models\BetLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class VivoGamingController extends Controller
{
    const PASSKEY = "7f1c5d";

    public function authenticate()
    {

        $time = time();
        $log = 'vivo_api_records';
        Log::channel($log)->debug("$time Function : " . __FUNCTION__);
        Log::channel($log)->debug("$time Params : " . json_encode(request()->all()));

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><VGSSYSTEM></VGSSYSTEM>');
        $data = [];
        $params = request()->all();
        $token = $params['token'] ?? '';
        $hash = $params['hash'] ?? '';
        $member_account = \App\Models\MemberAccount::where('password', $token)->first();

        if ($this->validate_token(__FUNCTION__, $params) != true || $member_account == null) {
            $requestData = ["TOKEN" => $token, "HASH" => $hash];
            $responseData = ["RESULT" => "FAILED", "CODE" => ($member_account == null) ? 400 : 500];

            $data["REQUEST"] = $requestData;
            $data["RESPONSE"] = $responseData;
            $data["TIME"] = now()->format('d M Y H:i:s');
            $xml = $this->arrayToXml($data, $xml);
            Log::channel($log)->debug("$time Response: " . json_encode($data));

            return $this->response($xml);
        }
        $member = \App\Models\Member::where('id', $member_account->member_id)->first();
        $requestData = ["TOKEN" => $token, "HASH" => $hash];
        $responseData = [
            "RESULT" => "OK",
            "USERID" => $member_account->username,
            "USERNAME" => $member_account->fullname,
            "CURRENCY" => "MYR",
            "BALANCE" => sprintf("%.2f", $member->balance)
        ];
        $data["REQUEST"] = $requestData;
        $data["TIME"] = now()->format('d M Y H:i:s');
        $data["RESPONSE"] = $responseData;
        $xml = $this->arrayToXml($data, $xml);

        Log::channel($log)->debug("$time Response: " . json_encode($data));
        return $this->response($xml);
    }

    public function get_balance()
    {

        $time = time();
        $log = 'vivo_api_balance_records';
        Log::channel($log)->debug("$time Function : " . __FUNCTION__);
        Log::channel($log)->debug("$time Params : " . json_encode(request()->all()));


        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><VGSSYSTEM></VGSSYSTEM>');
        $data = [];
        $params = request()->all();
        $userId = $params['userId'] ?? '';
        $hash = $params['hash'] ?? '';
        $member_account = \App\Models\MemberAccount::where('username', $userId)->first();
        if (!$this->validate_token(__FUNCTION__, $params) || $member_account == null) {

            $requestData = ["USERID" => $userId, "HASH" => $hash];
            $responseData = ["RESULT" => "FAILED", "CODE" => ($member_account == null) ? 310 : 500];
            $data["REQUEST"] = $requestData;
            $data["TIME"] = now()->format('d M Y H:i:s');
            $data["RESPONSE"] = $responseData;
            $xml = $this->arrayToXml($data, $xml);
            Log::channel($log)->debug("$time Response: " . json_encode($data));
            return $this->response($xml);
        }


        //TODO: need to get balance from member
        $requestData = ["USERID" => $userId, "HASH" => $hash];
        $responseData = ["RESULT" => "OK", "BALANCE" => sprintf("%.2f", $member_account->member->balance)];
        $data["REQUEST"] = $requestData;
        $data["TIME"] = now()->format('d M Y H:i:s');
        $data["RESPONSE"] = $responseData;
        $xml = $this->arrayToXml($data, $xml);

        Log::channel($log)->debug("$time Response: " . json_encode($data));
        return $this->response($xml);
    }

    public function change_balance()
    {

        $time = time();
        $log = 'vivo_api_transfer_records';
        Log::channel($log)->debug("$time Function : " . __FUNCTION__);
        Log::channel($log)->debug("$time Params : " . json_encode(request()->all()));

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><VGSSYSTEM></VGSSYSTEM>');
        $data = [];
        $params = request()->all();
        $userId = $params['userId'] ?? '';
        $amount = $params['Amount'] ?? '';
        $transactionId = $params['TransactionID'] ?? '';
        $trnType = $params['TrnType'] ?? '';
        $trnDescription = $params['TrnDescription'] ?? '';
        $roundId = $params['roundId'] ?? '';
        $gameId = $params['gameId'] ?? '';
        $history = $params['History'] ?? '';
        $isRoundFinished = $params['isRoundFinished'] ?? '';
        $hash = $params['hash'] ?? '';


        $member_account = \App\Models\MemberAccount::where('username', $userId)->first();
        if (!$this->validate_token(__FUNCTION__, $params) || $member_account == null) {
            $requestData = [
                "USERID" => $userId,
                "AMOUNT" => $amount,
                "TRANSACTIONID" => $transactionId,
                "TRNTYPE" => $trnType,
                "GAMEID" => $gameId,
                "ROUNDID" => $roundId,
                "TRNDESCRIPTION" => $trnDescription,
                "HISTORY" => $history,
                "ISROUNDFINISHED" => $isRoundFinished,
                "HASH" => $hash
            ];
            $responseData = ["RESULT" => "FAILED", "CODE" => ($member_account == null) ? 310 : 500];
            $data["REQUEST"] = $requestData;
            $data["TIME"] = now()->format('d M Y H:i:s');
            $data["RESPONSE"] = $responseData;
            $xml = $this->arrayToXml($data, $xml);
            Log::channel($log)->debug("$time Response: " . json_encode($data));

            return $this->response($xml);
        }

        if ($amount < 0) {
            $requestData = [
                "USERID" => $userId,
                "AMOUNT" => $amount,
                "TRANSACTIONID" => $transactionId,
                "TRNTYPE" => $trnType,
                "GAMEID" => $gameId,
                "ROUNDID" => $roundId,
                "TRNDESCRIPTION" => $trnDescription,
                "HISTORY" => $history,
                "ISROUNDFINISHED" => $isRoundFinished,
                "HASH" => $hash
            ];
            $responseData = ["RESULT" => "FAILED", "CODE" => 301];
            $data["REQUEST"] = $requestData;
            $data["TIME"] = now()->format('d M Y H:i:s');
            $data["RESPONSE"] = $responseData;
            $xml = $this->arrayToXml($data, $xml);
            Log::channel($log)->debug("$time Response: " . json_encode($data));

            return $this->response($xml);
        }

        if (strpos($history, 'BLACKJACK') !== false) {
            //get backjack type
            $result = explode(';', $history);
            $blackJackBetType = $result[0];

            $semicolonPos = strpos($history, ';');
            $seatId = substr($history, $semicolonPos + 1);
            $bet_log = BetLog::where('bet_id', 'VG_' . $userId . $roundId . $seatId . $blackJackBetType)->first();
            if ($bet_log == null) {
                $bet_log = BetLog::updateOrCreate([
                    'bet_id' => 'VG_' . $userId . $roundId . $seatId . $blackJackBetType,
                ], [
                    'product' => 'VG',
                    'game' => $params['gameId'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $member_account->username,
                    'stake' => 0,
                    'valid_stake' => 0,
                    'payout' => 0,
                    'winlose' => 0,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => "PENDING",
                    'bet_status' => "PENDING",
                    'account_date' => now()->format('Y-m-d'),
                    'round_at' => now()->format('d M Y H:i:s'),
                    'modified_at' => now()->format('d M Y H:i:s'),
                    'bet_detail' => json_encode(request()->all()),
                    'is_settle' => false,
                ]);
            }
        } else {
            $bet_log = BetLog::where('bet_id', 'VG_' . $userId . $roundId)->first();
            if ($bet_log == null) {
                $bet_log = BetLog::updateOrCreate([
                    'bet_id' => 'VG_' . $userId . $roundId,
                ], [
                    'product' => 'VG',
                    'game' => $params['gameId'],
                    'category' => Product::CATEGORY_LIVE,
                    'username' => $member_account->username,
                    'stake' => 0,
                    'valid_stake' => 0,
                    'payout' => 0,
                    'winlose' => 0,
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => "PENDING",
                    'bet_status' => "PENDING",
                    'account_date' => now()->format('Y-m-d'),
                    'round_at' => now()->format('d M Y H:i:s'),
                    'modified_at' => now()->format('d M Y H:i:s'),
                    'bet_detail' => json_encode(request()->all()),
                    'is_settle' => false,
                ]);
            }
        }
        

        $record = \App\Models\SeamlessRecord::where('transaction_id', $userId . $roundId)
            ->where('wager_id', $transactionId)
            ->where('game', 'vivo')->first();

        if ($record == null) {
            // if bet and user balance is not enough, else pass and create a new bet log
            $record = \App\Models\SeamlessRecord::updateOrCreate([
                'transaction_id' => $userId . $roundId,
                'wager_id' => $transactionId
            ], [
                'status' => \App\Models\SeamlessRecord::STATUS_PENDING
            ]);

            try {
                if ($params['TrnType'] == 'BET' && sprintf("%.2f", $member_account->member->balance) < $amount) {
                    $responseData = ["RESULT" => "FAILED", "CODE" => 300];
                    $requestData = [
                        "USERID" => $userId,
                        "AMOUNT" => $amount,
                        "TRANSACTIONID" => $transactionId,
                        "TRNTYPE" => $trnType,
                        "GAMEID" => $gameId,
                        "ROUNDID" => $roundId,
                        "TRNDESCRIPTION" => $trnDescription,
                        "HISTORY" => $history,
                        "ISROUNDFINISHED" => $isRoundFinished,
                        "HASH" => $hash
                    ];
                    $data["REQUEST"] = $requestData;
                    $data["TIME"] = now()->format('d M Y H:i:s');
                    $data["RESPONSE"] = $responseData;
                    $xml = $this->arrayToXml($data, $xml);
                    Log::channel($log)->debug("$time Response: " . json_encode($data));
                    $record->update([
                        'status' => \App\Models\SeamlessRecord::STATUS_SUCCESS,
                        'request' => $requestData,
                        'response' => $responseData
                    ]);
                    return $this->response($xml);
                }

                if ($params['TrnType'] == "BET") {
                    $member_account->member->decrement('balance', $amount);
                    $bet_log->update(['stake' => $bet_log['stake'] + $amount, 'valid_stake' => $bet_log['valid_stake'] + $amount]);
                    $record->update(['status' => \App\Models\SeamlessRecord::STATUS_SUCCESS]);
                }

                if ($params['TrnType'] == "WIN") {
                    if (($amount - $bet_log->stake) > 0) {
                        $payout_status = "WIN";
                    } elseif ($bet_log->stake == $amount) {
                        $payout_status = "DRAW";
                    } else {
                        $payout_status = "LOSE";
                    }
                    $bet_status = "SETTLED";
                    $member_account->member->increment('balance', $amount);
                    $bet_log->update([
                        'payout' => $amount,
                        'winlose' => $bet_log->stake - $amount,
                        'payout_status' => $payout_status,
                        'bet_status' => $bet_status,
                        'bet_detail' => $params
                    ]);
                    $record->update(['status' => \App\Models\SeamlessRecord::STATUS_SUCCESS]);
                }

                if ($params['TrnType'] == "CANCELED_BET") {
                    $payout_status = "CANCELED_BET";
                    $bet_status = "CANCELED_BET";
                    $member_account->member->increment('balance', $amount);
                    $bet_log->update([
                        'payout' => $amount,
                        'winlose' => $bet_log->stake - $amount,
                        'payout_status' => $payout_status,
                        'bet_status' => $bet_status,
                        'bet_detail' => $params
                    ]);
                    $record->update(['status' => \App\Models\SeamlessRecord::STATUS_SUCCESS]);
                }


            } catch (\Throwable $e) {
                
                $requestData = [
                    "USERID" => $userId,
                    "AMOUNT" => $amount,
                    "TRANSACTIONID" => $transactionId,
                    "TRNTYPE" => $trnType,
                    "GAMEID" => $gameId,
                    "ROUNDID" => $roundId,
                    "TRNDESCRIPTION" => $trnDescription,
                    "HISTORY" => $history,
                    "ISROUNDFINISHED" => $isRoundFinished,
                    "HASH" => $hash
                ];
                $responseData = ["RESULT" => "FAILED", "CODE" => 301];
                $data["REQUEST"] = $requestData;
                $data["TIME"] = now()->format('d M Y H:i:s');
                $data["RESPONSE"] = $responseData;
                $xml = $this->arrayToXml($data, $xml);
                $record->update(['status' => \App\Models\SeamlessRecord::STATUS_FAIL
                ,'request' => json_encode($requestData)
                ,'response' => json_encode($responseData)]);
                Log::channel($log)->debug("$time Response: " . json_encode($data));

                return $this->response($xml);
            }
        }

        $requestData = [
            "USERID" => $userId,
            "AMOUNT" => $amount,
            "TRANSACTIONID" => $transactionId,
            "TRNTYPE" => $trnType,
            "GAMEID" => $gameId,
            "ROUNDID" => $roundId,
            "TRNDESCRIPTION" => $trnDescription,
            "HISTORY" => $history,
            "ISROUNDFINISHED" => $isRoundFinished,
            "HASH" => $hash
        ];
        $responseData = [
            "RESULT" => "OK",
            "BALANCE" => sprintf("%.2f", $member_account->member->balance),
            "ECSYSTEMTRANSACTIONID" => $record->wager_id
        ];
        $data["REQUEST"] = $requestData;
        $data["TIME"] = now()->format('d M Y H:i:s');
        $data["RESPONSE"] = $responseData;
        $record->update([
            'request' => $requestData,
            'response' => $responseData
        ]);
        $xml = $this->arrayToXml($data, $xml);

        Log::channel($log)->debug("$time Response: " . json_encode($data));

        return $this->response($xml);

    }

    public function request_status()
    {

        $time = time();
        $log = 'vivo_api_ticket_records';
        Log::channel($log)->debug("$time Function : " . __FUNCTION__);
        Log::channel($log)->debug("$time Params : " . json_encode(request()->all()));

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><VGSSYSTEM></VGSSYSTEM>');
        $data = [];
        $params = request()->all();
        $userId = $params['userId'] ?? '';
        $hash = $params['hash'] ?? '';
        $casinoTransactionId = $params['casinoTransactionId'] ?? '';
        $member_account = \App\Models\MemberAccount::where('username', $userId)->first();

        if (!$this->validate_token(__FUNCTION__, $params) || $member_account == null) {
            $requestData = [
                "USERID" => $userId,
                "CASINOTRANSACTIONID" => $casinoTransactionId,
                "HASH" => $hash
            ];
            $responseData = ["RESULT" => "FAILED", "CODE" => ($member_account == null) ? 310 : 500];
            $timeData = now()->format('d M Y H:i:s');
            $data["REQUEST"] = $requestData;
            $data["TIME"] = $timeData;
            $data["RESPONSE"] = $responseData;
            $xml = $this->arrayToXml($data, $xml);
            Log::channel($log)->debug("$time Response: " . json_encode($data));

            return $this->response($xml);
        }

        $record = \App\Models\SeamlessRecord::where('wager_id', $casinoTransactionId)
            ->where('game', 'vivo')->first();

        if (!$record) {
            $requestData = [
                "USERID" => $userId,
                "CASINOTRANSACTIONID" => $casinoTransactionId,
                "HASH" => $params['hash']
            ];
            $responseData = ["RESULT" => "FAILED", "CODE" => 302];
            $timeData = now()->format('d M Y H:i:s');
            $data["REQUEST"] = $requestData;
            $data["TIME"] = $timeData;
            $data["RESPONSE"] = $responseData;
            $xml = $this->arrayToXml($data, $xml);
            Log::channel($log)->debug("$time Response: " . json_encode($data));

            return $this->response($xml);
        }

        if ($record->status == \App\Models\SeamlessRecord::STATUS_FAIL || $record->status == \App\Models\SeamlessRecord::STATUS_PENDING) {
            $requestData = [
                "USERID" => $userId,
                "CASINOTRANSACTIONID" => $casinoTransactionId,
                "HASH" => $params['hash']
            ];
            $responseData = ["RESULT" => "FAILED", "CODE" => 300];
            $timeData = now()->format('d M Y H:i:s');
            $data["REQUEST"] = $requestData;
            $data["TIME"] = $timeData;
            $data["RESPONSE"] = $responseData;
            $xml = $this->arrayToXml($data, $xml);
            Log::channel($log)->debug("$time Response: " . json_encode($data));

            return $this->response($xml);
        }

        $requestData = [
            "USERID" => $userId,
            "CASINOTRANSACTIONID" => $casinoTransactionId,
            "HASH" => $params['hash']
        ];
        $responseData = ["RESULT" => "OK", "ECSYSTEMTRANSACTIONID" => $casinoTransactionId];
        $timeData = now()->format('d M Y H:i:s');
        $data["REQUEST"] = $requestData;
        $data["TIME"] = $timeData;
        $data["RESPONSE"] = $responseData;
        $xml = $this->arrayToXml($data, $xml);
        Log::channel($log)->debug("$time Response: " . json_encode($data));

        return $this->response($xml);

    }

    public function validate_token($function, $params)
    {
        // $hash = $params['hash'] ?? '';
        // $md5String = '';
        // if ($function == "authenticate") {
        //     $md5String = md5(($params['token'] ?? '') . SELF::PASSKEY);
        // }
        // if ($function == "get_balance") {
        //     $md5String = md5(($params['userId'] ?? '') . SELF::PASSKEY);
        // }
        // if ($function == "change_balance") {
        //     $md5String = md5(($params['userId'] ?? '') . ($params['Amount'] ?? '') . ($params['TrnType'] ?? '') . ($params['TrnDescription'] ?? '') . ($params['roundId'] ?? '') . ($params['gameId'] ?? '') . ($params['History'] ?? '') . SELF::PASSKEY);
        // }
        // if ($function == "request_status") {
        //     $md5String = md5(($params['userId'] ?? '') . ($params['CasinoTransactionID'] ?? '') . SELF::PASSKEY);
        // }
        // Log::debug("function: ". $function . " md5String: " . $md5String . " hash: " . $hash);
        // return $hash == $md5String;

        return true;

    }

    function arrayToXml($data, $xml_data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // If the value is an array, create a new element and call the function recursively
                $subnode = $xml_data->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                // If the value is a simple string, add it as a child element
                $xml_data->addChild("$key", htmlspecialchars("$value"));
            }
        }

        return $xml_data;
    }

    public function response($xml)
    {
        return response($xml->asXml(), 200, [
            'Content-Type' => 'text/xml'
        ]);
    }
}