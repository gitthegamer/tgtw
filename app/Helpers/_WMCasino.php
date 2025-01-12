<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_WMCasinoController;

class _WMCasino
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $memberCode = str_replace(' ', '', $member->code);
        $memberCode = substr($memberCode, 0, 20);
        $response = _WMCasinoController::init("MemberRegister", [
            'cmd' => 'MemberRegister',
            'vendorId' => config('api.WM_VENDOR_ID'),
            'signature' => config('api.WM_KEY'),
            'user' =>  $memberCode,
            'password' => $password,
            'username' =>  $memberCode,
            'timestamp' => SELF::getTimeStamp(),
            'syslang' => 1,
        ]);


        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $memberCode,
            'username' => $memberCode,
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
        $response = _WMCasinoController::init("GetBalance", [
            'cmd' => 'GetBalance',
            'vendorId' => config('api.WM_VENDOR_ID'),
            'signature' => config('api.WM_KEY'),
            'user' =>  $member_account->account,
            'timestamp' => SELF::getTimeStamp(),
            'syslang' => 1,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['result'];
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
        $response = _WMCasinoController::init("ChangeBalance", [
            'cmd' => 'ChangeBalance',
            'vendorId' => config('api.WM_VENDOR_ID'),
            'signature' => config('api.WM_KEY'),
            'user' =>  $member_account->account,
            'money' => $transfer->amount,
            'order' => $transfer->uuid,
            'timestamp' => SELF::getTimeStamp(),
            'syslang' => 1,
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
        $response = _WMCasinoController::init("ChangeBalance", [
            'cmd' => 'ChangeBalance',
            'vendorId' => config('api.WM_VENDOR_ID'),
            'signature' => config('api.WM_KEY'),
            'user' =>  $member_account->account,
            'money' => -abs($transfer->amount),
            'order' => $transfer->uuid,
            'timestamp' => SELF::getTimeStamp(),
            'syslang' => 1,
        ]);

        if (!$response['status']) {
            return false;
        }

        // $response = _WMCasinoController::init("LogoutGame", [
        //     'cmd' => 'LogoutGame',
        //     'vendorId' => config('api.WM_VENDOR_ID'),
        //     'signature' => config('api.WM_KEY'),
        //     'user' =>  $member_account->account,
        //     'timestamp' => SELF::getTimeStamp(),
        //     'syslang' => 1,
        // ]);

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

        $response = _WMCasinoController::init("GetMemberTradeReport", [
            'cmd' => 'GetMemberTradeReport',
            'vendorId' => config('api.WM_VENDOR_ID'),
            'signature' => config('api.WM_KEY'),
            'user' =>  $member_account->account,
            'order' => $transfer->uuid,
            'timestamp' => SELF::getTimeStamp(),
            'syslang' => 1,
        ]);

        if (!$response['status']) {
            Helpers::sendNotification(json_encode($response));

            if ($response['data'] == null || $response['data']['errorCode'] == 10501) {
                return [
                    'status' => Transfer::STATUS_IGNORE,
                    'remark' => json_encode($response),
                ];
            }
            return false;
        }

        if ($response['data']['errorCode'] == 0) {
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

      


        $response = _WMCasinoController::init("SigninGame", [
            'cmd' => 'SigninGame',
            'vendorId' => config('api.WM_VENDOR_ID'),
            'signature' => config('api.WM_KEY'),
            'user' =>  $member_account->account,
            'password' => $member_account->password,
            'lang' => _WMCasinoController::getLocale(),
            'timestamp' => SELF::getTimeStamp(),
            'syslang' => 1,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['result'],
        ];
    }

    public static function getBets($startdate, $enddate, $page = 1)
    {
        $allBets = [];

        $response = _WMCasinoController::init("GetDateTimeReport", [
            'cmd' => 'GetDateTimeReport',
            'vendorId' => config('api.WM_VENDOR_ID'),
            'signature' => config('api.WM_KEY'),
            'startTime' => $startdate,
            'endTime' => $enddate,
            'datatype' => 2,
            'timetype' => 0,
            'timestamp' => SELF::getTimeStamp(),
            'syslang' => 1,
        ]);

        if (!$response['status']) {
            return false;
        }

        if (!$response['data']['errorCode'] == 0) {
            return [];
        }

        $bets = $response['data']['result'];
        if (!empty($bets)) {
            $allBets = array_merge($allBets, $bets);
        }
        return $allBets;
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

    public static function checkKioskBalance()
    {
        $response = _WMCasinoController::init("GetAgentBalance", [
            'cmd' => 'GetAgentBalance',
            'vendorId' => config('api.WM_VENDOR_ID'),
            'signature' => config('api.WM_KEY'),
            'timestamp' => SELF::getTimeStamp(),
            'syslang' => 1,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data']['result'];
    }
}
