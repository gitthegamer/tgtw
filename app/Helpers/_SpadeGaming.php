<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_SpadeGamingController;

class _SpadeGaming
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $memberCode = str_replace(' ', '', $member->code);
        $memberCode = substr($memberCode, 0, 20);
        $response = _SpadeGamingController::init("wallet/createMember", [
            'cert' => config('api.AWC_CERT'),
            'agentId' => config('api.AWC_USER_ID'),
            'userId' => $member->code,
            'currency' => "MYR",
            'betLimit' => "", //'{"SEXYBCRT":{"LIVE":{"limitId":[310103]}},"PP":{"LIVE":{"limitId":["G1"]}}}',
            'language' => "en",
            'userName' => $memberCode,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => strtolower($member->code),
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
        $response = _SpadeGamingController::init("wallet/getBalance", [
            'cert' => config('api.AWC_CERT'),
            'agentId' => config('api.AWC_USER_ID'),
            "alluser" => 0,
            'userIds' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['count'] == 0) {
            return 0;
        }

        return $response['data']['results'][0]['balance'];
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
        $response = _SpadeGamingController::init("wallet/deposit", [
            'cert' => config('api.AWC_CERT'),
            'agentId' => config('api.AWC_USER_ID'),
            'userId' => $member_account->username,
            'txCode' => $transfer->uuid,
            'transferAmount' => $transfer->amount
        ]);

        if (!$response['status']) {
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
        $response = _SpadeGamingController::init("wallet/withdraw", [
            'cert' => config('api.AWC_CERT'),
            'agentId' => config('api.AWC_USER_ID'),
            'userId' => $member_account->username,
            'txCode' => $transfer->uuid,
            'withdrawType' => 1,
            'transferAmount' => $transfer->amount
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


        $response = _SpadeGamingController::init("wallet/checkTransferOperation", [
            'cert' => config('api.AWC_CERT'),
            'agentId' => config('api.AWC_USER_ID'),
            'txCode' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['txStatus'] == 0) {
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

        $response = _SpadeGamingController::init("wallet/login", [
            'cert' => config('api.AWC_CERT'),
            'agentId' => config('api.AWC_USER_ID'),
            'userId' => $member_account->username,
            'isMobileLogin' => $isMobile,
            'externalURL' => config('api.MONEY_URL'),
            'platform' => 'SPADE',
            'gameType' => 'SLOT',
            'gameForbidden' => '{"JILI":{"SLOT":["ALL"]},"PP":{"SLOT":["ALL"],"LIVE":["ALL"]},"FC":{"SLOT":["ALL"]},"JDB":{"SLOT":["ALL"]}}',
            'language' => _SpadeGamingController::getLocale(),
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['url'],
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getTimestamp()
    {
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
