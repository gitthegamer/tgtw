<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_AllbetController;

class _Allbet
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        $response = _AllbetController::init("CheckOrCreate", [
            'agent' => config('api.AB_AGENT_USERNAME_LIVE'),
            'player' => $member->code . config('api.AB_SUFFIX_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['resultCode'] == 'PLAYER_EXIST') {
            return $member->member_accounts()->updateOrCreate([
                'member_id' => $member->id,
                'product_id' => $member->product_id,
            ], [
                'account' => $member->code . config('api.AB_SUFFIX_LIVE'),
                'username' => $member->code . config('api.AB_SUFFIX_LIVE'),
                'password' => $password,
            ]);
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $response['data']['data']['player'],
            'username' => $response['data']['data']['player'],
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

        $response = _AllbetController::init("GetBalances", [
            'agent' => config('api.AB_AGENT_USERNAME_LIVE'),
            'pageSize' => 1000,
            "pageIndex" => 1,
            'recursion' => '0',
            'players' => [$member_account->username]
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['list'][0]['amount'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $sn = config('api.AB_API_OPERATOR_ID_LIVE') . SELF::random13digit();
        Transfer::where('uuid', $transfer->uuid)->update([
            'uuid' => $sn,
        ]);

        $response = _AllbetController::init("Transfer", [
            'sn' => $sn,
            'agent' => config('api.AB_AGENT_USERNAME_LIVE'),
            'player' => $member_account->username,
            'type' => 1,
            "amount" => $transfer->amount,
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
        $sn = config('api.AB_API_OPERATOR_ID_LIVE') . SELF::random13digit();
        Transfer::where('uuid', $transfer->uuid)->update([
            'uuid' => $sn,
        ]);

        $response = _AllbetController::init("Transfer", [
            'sn' => $sn,
            'agent' => config('api.AB_AGENT_USERNAME_LIVE'),
            'player' => $member_account->username,
            'type' => 0,
            "amount" => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _AllbetController::init("GetBalances", [
            'agent' => config('api.AB_AGENT_USERNAME_LIVE'),
            'pageSize' => 1000,
            "pageIndex" => 1,
            'recursion' => '0',
            'players' => [$member_account->username]
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['list'][0]['amount'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $sn = config('api.AB_API_OPERATOR_ID_LIVE') . SELF::random13digit();
        Transfer::where('uuid', $transfer->uuid)->update([
            'uuid' => $sn,
        ]);

        $response = _AllbetController::init("Transfer", [
            'sn' => $sn,
            'agent' => config('api.AB_AGENT_USERNAME_LIVE'),
            'player' => $member_account->username,
            'type' => 0,
            "amount" => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $sn = config('api.AB_API_OPERATOR_ID_LIVE') . SELF::random13digit();
        Transfer::where('uuid', $transfer->uuid)->update([
            'uuid' => $sn,
        ]);

        $response = _AllbetController::init("Transfer", [
            'sn' => $sn,
            'agent' => config('api.AB_AGENT_USERNAME_LIVE'),
            'player' => $member_account->username,
            'type' => 1,
            "amount" => $transfer->amount,
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
        };
        $response = _AllbetController::init("GetTransferState", [
            'sn' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['data']['transferState'] == 0) {
            return [
                'status' => Transfer::STATUS_IN_PROGRESS,
                'remark' => json_encode($response),
            ];
        }

        if ($response['data']['data']['transferState'] == 2) {
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

        $response = _AllbetController::init("Login", [
            "player" => $member_account->username,
            'returnUrl' => route('home')
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        SELF::bet_limit($member_account, $member);

        return [
            'url' => $response['data']['data']['gameLoginUrl'],
        ];
    }

    public static function bet_limit($member_account, $member)
    {
        $bet_limit = $member->getBetLimit();
        if (!$bet_limit) {
            return;
        }

        _AllbetController::init("ModifyPlayerSetting", [
            'player' => $member_account->username,
            'generalHandicaps' => [$bet_limit['code'] ?? "5_3K"],
        ]);
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        $response = _AllbetController::init("PagingQueryBetRecords", [
            "startDateTime" => $startDate,  // GMT + 8
            "endDateTime" => $endDate, // GMT + 8
            "pageNum" => $page,
            "pageSize" => 1000,
        ]);

        if (!$response['status']) {
            return false;
        }

        if (count($response['data']['data']['list']) > 0 && ($response['data']['data']['total'] / 1000) > $page) {
            usleep(100000);
            return array_merge($response['data']['data']['list'], SELF::getBets($startDate, $endDate, $page + 1));
        }

        usleep(100000);
        return $response['data']['data']['list'];
    }

    public static function getTimestamp()
    {
        return time();
    }

    public static function random13digit()
    {
        $randomNumber = mt_rand(1000000000000, 9999999999999);
        return $randomNumber;
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
