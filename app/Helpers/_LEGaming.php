<?php

namespace App\Helpers;

use App\Models\Game;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_LEGamingController;

class _LEGaming
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $response = _LEGamingController::init("CreateUser", [
            'agent' => config('api.LEGAMING_AGENT'),
            'timestamp' => self::getTimestamp(),
            's' => 0,
            'account' => $member->code,
            'money' => 0,
            'orderid' => config('api.LEGAMING_AGENT') . Carbon::now()->format('YmdHisv') . $member->code,
            'ip' => request()->ip(),
            'lineCode' => Str::random(8, 'alnum'),
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
        if ($member_account) {
            return $member_account;
        }

        return SELF::create($member);
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
        $response = _LEGamingController::init("GetBalance", [
            'agent' => config('api.LEGAMING_AGENT'),
            'timestamp' => self::getTimestamp(),
            's' => 7,
            'account' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['d']['totalMoney'];
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
        $transfer->uuid = config('api.LEGAMING_AGENT') . Carbon::now()->format('YmdHisv') . $member_account->username;
        $transfer->save();

        $response = _LEGamingController::init("Deposit", [
            'agent' => config('api.LEGAMING_AGENT'),
            'timestamp' => self::getTimestamp(),
            's' => 2,
            'account' => $member_account->username,
            'money' => $transfer->amount,
            'orderid' => $transfer->uuid,
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
        $transfer->uuid = config('api.LEGAMING_AGENT') . Carbon::now()->format('YmdHisv') . $member_account->username;
        $transfer->save();

        $response = _LEGamingController::init("Withdraw", [
            'agent' => config('api.LEGAMING_AGENT'),
            'timestamp' => self::getTimestamp(),
            's' => 3,
            'account' => $member_account->username,
            'money' => $transfer->amount,
            'orderid' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }

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

        $response = _LEGamingController::init("CheckTransaction", [
            'agent' => config('api.LEGAMING_AGENT'),
            'timestamp' => self::getTimestamp(),
            's' => 4,
            'orderid' => $uuid,
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


        $response = _LEGamingController::init("Launch", [
            'agent' => config('api.LEGAMING_AGENT'),
            'timestamp' => self::getTimestamp(),
            's' => 0,
            'account' => $member_account->username,
            'money' => 0,
            'orderid' => config('api.LEGAMING_AGENT') . Carbon::now()->format('YmdHisv') . $member_account->username,
            'ip' => request()->ip(),
            'lineCode' => Str::random(8, 'alnum'),
            'lang' => _LEGamingController::getLocale(),
        ]);

        if (!$response['status']) {
            return product::ERROR_PROVIDER_MAINTENANCE;
        }
        return [
            'url' => $response['data']['d']['url']
        ];
    }

    public static function getBets($startdate, $enddate)
    {
        $response = _LEGamingController::init("GetBets", [
            'agent' => config('api.LEGAMING_AGENT'),
            'timestamp' => self::getTimestamp(),
            's' => 6,
            'startTime' => $startdate,
            'endTime' => $enddate,
        ]);

        if (!$response['status']) {
            return false;
        }

        // 检查 'list' 键是否存在
        if (!isset($response['data']['d']['list'])) {
            return []; // 返回空数组而不是 false
        }

        return $response['data']['d']['list'];
    }


    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getTimestamp()
    {
        return (int)(microtime(true) * 1000);
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
