<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_WCasinoController;


class _WCasino
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $memberCode = str_replace(' ', '', $member->code);
        $memberCode = substr($memberCode, 0, 20);

        $array = array(
            'appid' => config('api.WCASINO_APPID'),
            'username' => $memberCode,
            'iscreate' => 1,
            'clienttype' => 2,
            'language' => _WCasinoController::getLocale(),
        );

        $response = _WCasinoController::init("login", [
            'appid' => config('api.WCASINO_APPID'),
            'username' => $memberCode,
            'iscreate' => 1,
            'clienttype' =>  2,
            'language' => _WCasinoController::getLocale(),
            'sign' => self::md5_encryption($array),
        ]);

        if ($response['status'] == false) {
            return false;
        }

        $memberAccount = $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $memberCode,
            'username' => $memberCode,
            'password' => $password,
        ]);

        // 设置投注限制
        SELF::bet_limit($memberAccount, $member);

        return $memberAccount;
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
        $response = _WCasinoController::init("user/balance", [
            'appid' => config('api.WCASINO_APPID'),
            'username' => $member_account->account,
            'sign' => SELF::md5_encryption([
                'appid' => config('api.WCASINO_APPID'),
                'username' => $member_account->account,
            ]),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['balance'];
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
        $response = _WCasinoController::init("user/dw", [
            'appid' => config('api.WCASINO_APPID'),
            'username' => $member_account->account,
            'tradeno' => $transfer->uuid,
            'amount' =>  abs($transfer->amount),
            'sign' => SELF::md5_encryption([
                'appid' => config('api.WCASINO_APPID'),
                'username' => $member_account->account,
                'tradeno' => $transfer->uuid,
                'amount' => abs($transfer->amount),
            ]),
        ]);

        if (!$response['status']) {
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
        $response = _WCasinoController::init("user/dw", [
            'appid' => config('api.WCASINO_APPID'),
            'username' => $member_account->account,
            'tradeno' => $transfer->uuid,
            'amount' =>  -abs($transfer->amount),
            'sign' => SELF::md5_encryption([
                'appid' => config('api.WCASINO_APPID'),
                'username' => $member_account->account,
                'tradeno' => $transfer->uuid,
                'amount' => -abs($transfer->amount),
            ]),
        ]);

        if (!$response['status']) {
            return false;
        }

        $response = _WCasinoController::init("kickout", [
            'appid' => config('api.WCASINO_APPID'),
            'username' => $member_account->account,
            'sign' => SELF::md5_encryption([
                'appid' => config('api.WCASINO_APPID'),
                'username' => $member_account->account,
            ]),
        ]);

        return true;
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

        $response = _WCasinoController::init("user/trade", [
            'appid' => config('api.WCASINO_APPID'),
            'username' => $member_account->account,
            'tradeno' => $transfer->uuid,
            'begintime' =>  Carbon::now()->subMinutes(5)->timestamp,
            'endtime' => Carbon::now()->timestamp,
            'index' => 0,
            'size' => 100,
            'sign' => SELF::md5_encryption([
                'appid' => config('api.WCASINO_APPID'),
                'username' => $member_account->account,
                'tradeno' => $transfer->uuid,
                'begintime' =>  Carbon::now()->subMinutes(5)->timestamp,
                'endtime' => Carbon::now()->timestamp,
                'index' => 0,
                'size' => 100,
            ]),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['result'] < 0) {
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

        $array = array(
            'appid' => config('api.WCASINO_APPID'),
            'username' => $member_account->account,
            'iscreate' => 1,
            'clienttype' => $isMobile ? 1 : 2,
            'language' => _WCasinoController::getLocale(),
        );

        $response = _WCasinoController::init("login", [
            'appid' => config('api.WCASINO_APPID'),
            'username' => $member_account->account,
            'iscreate' => 1,
            'clienttype' =>  $isMobile ? 1 : 2,
            'language' => _WCasinoController::getLocale(),
            'sign' => self::md5_encryption($array),
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        // 设置投注限制
        SELF::bet_limit($member_account, $member);

        return [
            'url' => $response['data']['openurl'],
        ];
    }

    public static function bet_limit($member_account, $member)
    {
        $bet_limit = $member->getBetLimit();
        if (!$bet_limit) {
            return;
        }

        $response = _WCasinoController::init("quota/set", [
            'appid' => config('api.WCASINO_APPID'),
            'username' => $member_account->account,
            'qids' => $bet_limit['code'] ?? config('api.WCASINO_BET_LIMIT'),
            'sign' => SELF::md5_encryption([
                'appid' => config('api.WCASINO_APPID'),
                'username' => $member_account->account,
                'qids' => $bet_limit['code'] ?? config('api.WCASINO_BET_LIMIT'),
            ]),
        ]);

        if (!$response['status']) {
            return false;
        }

        return true;
    }

    public static function bet_limit_list()
    {
        $response = _WCasinoController::init("quota/list", [
            'appid' => config('api.WCASINO_APPID'),
            'sign' => SELF::md5_encryption([
                'appid' => config('api.WCASINO_APPID'),
            ]),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
    }

    public static function getBets($startdate, $enddate, $page = 0)
    {
        $allBets = [];

        $response = _WCasinoController::init("record/bets/detail", [
            'appid' => config('api.WCASINO_APPID'),
            'begintime' => $startdate,
            'endtime' => $enddate,
            'index' => $page,
            'size' => 2000,
            'sign' => SELF::md5_encryption([
                'appid' => config('api.WCASINO_APPID'),
                'begintime' => $startdate,
                'endtime' => $enddate,
                'index' => $page,
                'size' => 2000,
            ]),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['result'] < 0) {
            return [];
        }

        $bets = $response['data']['array'];
        if (!empty($bets)) {
            $allBets = array_merge($allBets, $bets);
        }

        if ($response['data']['arraysize'] >= 2000 && $page < $response['data']['arraysize'] / 2000) {
            $allBets = array_merge($allBets, SELF::getBets($startdate, $enddate, $page + 1));
        }

        return $allBets;
    }


    public static function getTimestamp()
    {
        return time();
    }

    public static function md5_encryption($array)
    {

        $secretKey = config('api.WCASINO_SECRET');
        // Assuming $array is equivalent to $request->all()
        $data = $array;
        // Convert all keys to lowercase
        $data = array_change_key_case($data, CASE_LOWER);
        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });

        // Sort the array by keys
        ksort($data);
        $dataStr = '';
        foreach ($data as $key => $value) {
            if ($key != 'sign') {
                $dataStr .= $key . '=' . $value . '&';
            }
        }

        // Remove the trailing '&'
        $dataStr = substr_replace($dataStr, "", -1);
        $encryptedKey = md5($dataStr . "&key=" . $secretKey);

        return $encryptedKey;
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
