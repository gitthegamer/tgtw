<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_M8Controller;

class _M8
{

    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $response = _M8Controller::init("player", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member->code
        ]);

        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => $member->code,
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

        $response = _M8Controller::init("balance", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member_account->username
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['result'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _M8Controller::init("deposit", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member_account->username,
            'serial' => $transfer->uuid,
            'amount' => $transfer->amount
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

        $response = _M8Controller::init("withdraw", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member_account->username,
            'serial' => $transfer->uuid,
            'amount' => $transfer->amount
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _M8Controller::init("balance", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member_account->username
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['result'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _M8Controller::init("withdraw", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member_account->username,
            'serial' => $transfer->uuid,
            'amount' => $transfer->amount
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _M8Controller::init("deposit", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member_account->username,
            'serial' => $transfer->uuid,
            'amount' => $transfer->amount
        ]);

        if (!$response['status']) {
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

        $response = _M8Controller::init("check_payment", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member_account->username,
            'serial' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => json_encode($response)
            ];
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => json_encode($response),
        ];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $response = _M8Controller::init("login", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'username' => $member_account->username,
            'accType' => 'MY',
            'lang' => _M8Controller::getLocale(),
            'ref' => route('home')
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $isMobile ? $response['data']['result']['login']['mobiurl']:$response['data']['result']['login']['weburl']
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBets()
    {
        $response = _M8Controller::init("fetch_result", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        if(is_string($response['data']['result'])){
            return [];
        }
            
        
        return $response['data']['result']['ticket'];
    }

    public static function updateBets($input){
        
        $response = _M8Controller::init("mark_fetched", [
            'agent' => config('api.M8_AGENT_LIVE'),
            'secret' => config('api.M8_SECRET_LIVE'),
            'fetch_ids' => $input
        ]);

        if (!$response['status']) {
            return false;
        }

        return true;
    }

    public static function getTimestamp(){
        return time();
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
}
