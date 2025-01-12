<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_Obet33Controller;

class _Obet33
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        
        $response = _Obet33Controller::init("api/sb/register", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),
            'userId' => config('api.OBET_AGENT_ID').$member->code,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => config('api.OBET_AGENT_ID').$member->code,
            'username' => config('api.OBET_AGENT_ID').$member->code,
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

        $response = _Obet33Controller::init("api/sb/bal", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),
            'userId' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Balance'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _Obet33Controller::init("api/sb/fund", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),
            'userId' => $member_account->username,
            'amount' => $transfer->amount,
            'serialNo' => $transfer->uuid,
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

        $response = _Obet33Controller::init("api/sb/fund", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),
            'userId' => $member_account->username,
            'amount' => $transfer->amount*-1,
            'serialNo' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
       $response = _Obet33Controller::init("api/sb/bal", [
        'apiKey' => config('api.OBET_SECRET_KEY'),
        'agentId' => config('api.OBET_AGENT_ID'),
        'userId' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Balance'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _Obet33Controller::init("api/sb/fund", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),
            'userId' => $member_account->username,
            'amount' => $transfer->amount*-1,
            'serialNo' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _Obet33Controller::init("api/sb/fund", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),
            'userId' => $member_account->username,
            'amount' => $transfer->amount,
            'serialNo' => $transfer->uuid,
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

        $response = _Obet33Controller::init("api/sb/fund/check", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),
            'userId' => $member_account->username,
            'serialNo' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => json_encode($response),
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
        
        $response = _Obet33Controller::init("api/sb/open", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),
            'userId' => $member_account->username,
            'lang' => _Obet33Controller::getLocale(),
            'mobile' => $isMobile ? 1 : 0,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }
        
        return [
            'url' => $response['data']['LoginUrl']
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBets($startDate, $endDate)
    {
        $response = _Obet33Controller::init("api/sb/betinfo", [
            'apiKey' => config('api.OBET_SECRET_KEY'),
            'agentId' => config('api.OBET_AGENT_ID'),   
            'lang' => _Obet33Controller::getLocale(),
            "startdate" => $startDate,
            "enddate" => $endDate,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
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
