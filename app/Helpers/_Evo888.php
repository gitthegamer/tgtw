<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_Evo888Controller;

class _Evo888
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        $response = _Evo888Controller::init("addUser", [
            'action' => 'addUser',
            'name' => str_replace(' ', '', $member->full_name),
            'passwd' => $password,
            'authcode' => config('api.EVO888_CLIENT_ID'),
            'time' => SELF::getTimeStamp(),
            'tel' => SELF::randomPhone(),
            'type' => 0,
            'desc' => 'create user'
        ]);

        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $response['data']['results']['username'],
            'username' => $response['data']['results']['username'],
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
        $account = SELF::check($member);
        return $account;
    }

    public static function balance($member)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _Evo888Controller::init("searchUser", [
            'action' => 'searchUser',
            'username' => $member_account->username,
            'time' => SELF::getTimeStamp(),
            'authcode' => config('api.EVO888_CLIENT_ID'),
            'type' => 0,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['results']['balance'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _Evo888Controller::init("setScore", [
            'action' => 'setScore',
            'username' => $member_account->username,
            'time' => SELF::getTimeStamp(),
            'type' => 0,
            'score' => $transfer->amount,
            'authcode' => config('api.EVO888_CLIENT_ID'),
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _Evo888Controller::init("setScore", [
            'action' => 'setScore',
            'username' => $member_account->username,
            'time' => SELF::getTimeStamp(),
            'score' => $transfer->amount * -1,
            'authcode' => config('api.EVO888_CLIENT_ID'),
            'type' => 0,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _Evo888Controller::init("searchUser", [
            'action' => 'searchUser',
            'username' => $member_account->username,
            'time' => SELF::getTimeStamp(),
            'authcode' => config('api.EVO888_CLIENT_ID'),
            'type' => 0,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['results']['balance'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _Evo888Controller::init("setScore", [
            'action' => 'setScore',
            'username' => $member_account->username,
            'time' => SELF::getTimeStamp(),
            'score' => $transfer->amount * -1,
            'authcode' => config('api.EVO888_CLIENT_ID'),
            'type' => 0,
        ]);

       

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {

        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _Evo888Controller::init("setScore", [
            'action' => 'setScore',
            'username' => $member_account->username,
            'time' => SELF::getTimeStamp(),
            'score' => $transfer->amount,
            'authcode' => config('api.EVO888_CLIENT_ID'),
            'type' => 0,
        ]);


        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function checkTransaction($uuid)
    {
        $transfer = Transfer::where('uuid', $uuid)->first();
        if (!$transfer) {
            return false;
        }

        $member_account = self::check($transfer->member);
        if (!$member_account) {
            return false;
        }

        $current_balance = $member_account->balance();
        
        if(($transfer->before_balance === 0) && ($current_balance === 0)){
            return [
                'status' => Transfer::STATUS_SUCCESS,
                'remark' => 'manual check',
            ];
        }

        if ($transfer->before_balance === $current_balance) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => 'manual check',
            ];
        }
    
        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => 'manual check',
        ];
    
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        // if (SELF::balance($member) === false) {
        //     return Product::ERROR_PROVIDER_MAINTENANCE;
        // }

        return [
            'member_account' => $member_account,
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getPlayerlist($startDate, $endDate){
        $response = _Evo888Controller::init("getTotalReport", [
            "action" => 'getTotalReport',
            'time' => SELF::getTimestamp(),
            'type' => 0,
            "sdate" => $startDate,
            "edate" => $endDate,
            "authcode" => config('api.EVO888_CLIENT_ID'),
        ]);

        if (!$response['status']) {
            return [];
        }

        return isset($response['data']['results']['report']) ? $response['data']['results']['report'] : [];
    }

    public static function getBets($startDate, $endDate, $username)
    {
        $response = _Evo888Controller::init("getUserGameLog", [
            "action" => 'getUserGameLog',
            'time' => SELF::getTimestamp(),
            "username" => $username,
            "sdate" => $startDate,
            "edate" => $endDate,
            "gametype" => 0,
            "authcode" => config('api.EVO888_CLIENT_ID'),

        ]);

        if (!$response['status']) {
            return false;
        }

        if (empty($response['data']['results'])) {
            return false;
        }

        return $response['data']['results']['gameLog'];
    }



    public static function randomPassword($len = 8)
    {
        if ($len < 8) {
            $len = 8;
        }

        $sets = array();
        $sets[] = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        $sets[] = '123456789';

        $password = '';

        foreach ($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
        }

        //use all characters to fill up to $len
        while (strlen($password) < $len) {
            //get a random set
            $randomSet = $sets[array_rand($sets)];

            //add a random char from the random set
            $password .= $randomSet[array_rand(str_split($randomSet))];
        }

        //shuffle the password string before returning!
        return str_shuffle($password);
    }

    public static function getTimeStamp()
    {
        $timestamp = microtime(true); // Get the current Unix timestamp with microseconds

        $timestamp *= 1000; // Multiply by 1000 to convert it to milliseconds

        $timestamp = round($timestamp);

        return $timestamp;
    }

    public static function randomPhone()
    {
        $phone = random_int(100000, 9999999);
        return $phone;
    }
}
