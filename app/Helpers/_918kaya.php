<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_918KayaController;

class _918kaya
{
    public static function create(Member $member)
    {
        $username = strtolower($member->code);
        $password = SELF::randomPassword();

        $response = _918KayaController::init("v1/accountcreate", [
            'agentID' => config('api.918KAYA_AGENT'),
            'accountName' => $username,
            'accountPW' => $password,
            'accountDisplay' => $displayname = mb_substr($member->username, 0, 64, 'UTF-8'),
            'timeStamp' => SELF::getTimestamp(),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $displayname,
            'username' => $username,
            'password' => $password,
        ]);
    }

    public static function check(Member $member)
    {
        $member_account = $member->member_accounts()->where('member_id', $member->id)->where('product_id', $member->product_id)->first();
        if (!$member_account) {
            return SELF::create($member);
        }

        return $member_account;
    }

    public static function resetPassword($product, $user)
    {
        return true;
    }

    public static function account(Member $member)
    {
        if ($account = SELF::check($member)) {
            return $account;
        }
        return SELF::create($member);
    }

    public static function balance($member)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return false;
        }

        return SELF::account_balance($member_account);
    }

    public static function account_balance($member_account)
    {
        $response = _918KayaController::init("v1/accountbalance", [
            'agentID' => config('api.918KAYA_AGENT'),
            'accountName' => $member_account->username,
            'timeStamp' => SELF::getTimestamp(),
        ]);
        if (!$response['status']) {
            return false;
        }

        if (!isset($response['data']['balance'])) {
            return false;
        }

        $balance = round($response['data']['balance'] / 10000, 2);
        return $balance;
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return false;
        }

        return SELF::account_deposit($member_account, $transfer);
    }

    public static function account_deposit($member_account, $transfer)
    {
        $transAmount = round($transfer->amount * 10000);
        $transid = SELF::generateTransactionId();
        $transfer->update(['unique_id' => $transid]);

        $response = _918KayaController::init("v1/transferdeposit", [
            'agentID' => config('api.918KAYA_AGENT'),
            'accountName' => $member_account->username,
            'transAmount' => $transAmount,
            'transAgentID' => $transid,
            'timeStamp' => SELF::getTimestamp(),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['rtStatus'] && $response['data']['rtStatus'] === 9) {
            return false;
        }

        return $response['status'];
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return false;
        }

        return SELF::account_withdrawal($member_account, $transfer);
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $transAmount = round($transfer->amount * 10000);
        $transid = SELF::generateTransactionId();

        $transfer->update(['unique_id' => $transid]);
        $response = _918KayaController::init("v1/transferwithdraw", [
            'agentID' => config('api.918KAYA_AGENT'),
            'accountName' => $member_account->username,
            'transAmount' => $transAmount,
            'transAgentID' => $transid,
            'timeStamp' => SELF::getTimestamp(),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['rtStatus'] && $response['data']['rtStatus'] === 9) {
            return false;
        }

        return $response['status'];
    }

    public static function checkTransaction($uuid)
    {
        $transfer =  Transfer::where('uuid', $uuid)->first();
        if (!$transfer) {
            return false;
        }

        $member_account = SELF::check($transfer->member);
        if (!$member_account) {
            return false;
        }

        $response = _918KayaController::init("v1/transfercheck", [
            'agentID' => config('api.918KAYA_AGENT'),
            'accountName' => $member_account->username,
            'transAgentID' => $transfer->unique_id,
            'timeStamp' => SELF::getTimestamp(),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['returncode'] != 0) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['data']['message'],
            ];
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => $response['data']['message'],
        ];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $game = Game::where('code', $gameid)->first();
        if (!$game) {
            return false;
        }

        $response = _918KayaController::init("v1/launchH5", [
            'agentID' => config('api.918KAYA_AGENT'),
            'accountName' => $member_account->username,
            'gamePlatformID' => config('api.918KAYA_GAMELIST_PLATFORM'),
            'gameID' => $gameid,
            'lang' => SELF::getLocale(),
            'menu' => '11111011',
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['url']
        ];
    }
    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function product_logs($date)
    {
        //end time need descrease 10 seconds
        $endTime = $date->copy()->subSeconds(10);
        $endTimeMillis = $endTime->timestamp * 1000;
        $startTime = $endTime->copy()->subMinutes(5);
        $startTimeMillis = $startTime->timestamp * 1000;


        $response = _918KayaController::init("v1/betlist", [
            'agentID' => config('api.918KAYA_AGENT'),
            'startUpdateTime' => $startTimeMillis,
            'endUpdateTime' => $endTimeMillis,
            'timeStamp' => SELF::getTimestamp(),
        ]);

        if (!$response['status']) {
            return false;
        }

        // Check if 'data' key exists in the response array
        if (!isset($response['data']) || !isset($response['data']['data'])) {
            return []; // or handle the error as needed
        }

        return $response['data']['data'];
    }

    public static function get_player_list($date)
    {
        return false;
    }

    public static function getTimestamp()
    {
        return time();
    }

    public static function randomPassword($minLength = 6, $maxLength = 10)
    {
        $length = mt_rand($minLength, $maxLength);

        $sets = array();
        $sets[] = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Uppercase letters excluding I, O, and similar characters
        $sets[] = 'abcdefghjkmnpqrstuvwxyz'; // Lowercase letters excluding i, l, and similar characters
        $sets[] = '123456789'; // Digits
        // $sets[] = '!@#$%^&*()-_+=|{}[]:;"\'<>,.?/~'; // Symbols

        $password = '';

        foreach ($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
        }

        while (strlen($password) < $length) {
            $randomSet = $sets[array_rand($sets)];
            $password .= $randomSet[array_rand(str_split($randomSet))];
        }

        return str_shuffle($password);
    }

    public static function generateTransactionId($length = 7)
    {
        // Define the character set for the transaction ID
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        // Get the total length of the character set
        $charLength = strlen($characters);

        // Initialize the transaction ID with the prefix "T"
        $transactionId = 'T';

        // Generate random characters to create the transaction ID
        for ($i = 0; $i < $length; $i++) {
            $transactionId .= $characters[rand(0, $charLength - 1)];
        }

        return $transactionId;
    }

    public static function getLocale()
    {
        if (app()->getLocale() == "en") {
            return "en_US";
        }
        if (app()->getLocale() == "cn") {
            return "zh_CN";
        }
        return "en_US";
    }
}
