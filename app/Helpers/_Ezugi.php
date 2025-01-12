<?php

namespace App\Helpers;

use App\Models\GameLogKey;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_EzugiController;
use Carbon\Carbon;

class _Ezugi
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        $response = _EzugiController::init("createMember.aspx", [
            'operatorcode' => config('api.EZUGI_OPERATOR_CODE'),
            'username' => $username = strtolower($member->code)
        ]);

        if ($response['status'] == false) {
            return false;
        }

        $response = _EzugiController::init("checkMemberProductUsername.aspx", [
            'operatorcode' => config('api.EZUGI_OPERATOR_CODE'),
            'providercode' => config('api.EZUGI_PROVIDER_CODE'),
            'username' => $username,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        if ($response['status']) {
            $providerUsername = $response['data']['data'];
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $username,
            'username' => $providerUsername,
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
        $response = _EzugiController::init("getBalance.aspx", [
            'operatorcode' => config('api.EZUGI_OPERATOR_CODE'),
            'providercode' => config('api.EZUGI_PROVIDER_CODE'),
            'username' => $member_account->account,
            'password' => $member_account->password,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return ($response['data']['balance']);
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
        $response = _EzugiController::init("makeTransfer.aspx", [
            'operatorcode' => config('api.EZUGI_OPERATOR_CODE'),
            'providercode' => config('api.EZUGI_PROVIDER_CODE'),
            'username' => $member_account->account,
            'password' => $member_account->password,
            'referenceid' => $transfer->uuid,
            'type' => '0',
            'amount' => $transfer->amount,
        ]);


        if ($response['status'] == false) {
            return false;
        }
        return true;
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
        $response = _EzugiController::init("makeTransfer.aspx", [
            'operatorcode' => config('api.EZUGI_OPERATOR_CODE'),
            'providercode' => config('api.EZUGI_PROVIDER_CODE'),
            'username' => $member_account->account,
            'password' => $member_account->password,
            'referenceid' => $transfer->uuid,
            'type' => '1',
            'amount' => $transfer->amount,
        ]);

        if ($response['status'] == false) {
            return false;
        }
        return true;
    }

    public static function checkTransaction($uuid)
    {
        $transfer = Transfer::where('uuid', $uuid)->first();
        if (!$transfer) {
            return false;
        }

        $member_account = SELF::check($transfer->member);
        if (!$member_account) {
            return false;
        }

        $response = _EzugiController::init("checkTransaction.ashx", [
            'operatorcode' => config('api.EZUGI_OPERATOR_CODE'),
            'referenceid' =>  $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }


        if ($response['data']['data']['status'] == 'FAILED') {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => json_encode($response),
            ];
        }

        if ($response['data']['data']['status'] == 'PROCESSING') {
            return [
                'status' => Transfer::STATUS_IN_PROGRESS,
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

        $response = _EzugiController::init("launchGames.aspx", [
            'operatorcode' => config('api.EZUGI_OPERATOR_CODE'),
            'providercode' => config('api.EZUGI_PROVIDER_CODE'),
            'username' => strtolower($member_account->account),
            'password' => $member_account->password,
            'lang' => _EzugiController::getLocale(),
            'type' => 'LC',
            'html5' => $isMobile ? '1' : '0',
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['gameUrl']
        ];
    }

    public static function getGameList()
    {
        $response = _EzugiController::init("getGameList.aspx", [
            'operatorcode' => config('api.EZUGI_OPERATOR_CODE'),
            'providercode' => config('api.EZUGI_PROVIDER_CODE'),
            'reformatJson' => 'yes'
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
    }

    public static function getBets($version_key = null)
    {
        if (!$version_key) {
            $version_key = cache()->get('ezugi_version_key.' . config('api.EZUGI_OPERATOR_CODE'), 0);
        }

        $response = _EzugiController::init("fetchbykey.aspx", [
            "operatorcode" => config('api.EZUGI_OPERATOR_CODE'),
            'versionkey' => $version_key,
            'providercode' => config('api.EZUGI_PROVIDER_CODE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['lastversionkey'] != null && $response['data']['lastversionkey'] != '' && $response['data']['lastversionkey'] != 0) {
            cache()->put(
                'ezugi_version_key.' . config('api.EZUGI_OPERATOR_CODE'),
                $response['data']['lastversionkey']
            );

            GameLogKey::create([
                'class' => _Ezugi::class,
                'key' => $response['data']['lastversionkey'],
            ]);
        }

        if ($response['data']['result'] == null || $response['data']['result'] == '') {
            return false;
        }

        return json_decode($response['data']['result'], true);
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

    public static function randomPhone($requiredLength = 7, $highestDigit = 7)
    {
        $sequence = '';
        for ($i = 0; $i < $requiredLength; ++$i) {
            $sequence .= mt_rand(0, $highestDigit);
        }
        $numberPrefixes = ['011', '012', '013', '014', '016', '017', '018', '019'];
        for ($i = 0; $i < 21; ++$i) {
            $phone = $numberPrefixes[array_rand($numberPrefixes)] . $sequence;
        }
        return $phone;
    }

    public static function getBizID()
    {
        $snowflake = new \Godruoyi\Snowflake\Snowflake();
        return $snowflake->id();
    }
}
