<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_CQ9Controller;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class _CQ9
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $response = _CQ9Controller::init("gameboy/player", [
            "account" => $member->code,
            'password' =>  $password,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $response['data']['data']['account'],
            'username' => $response['data']['data']['account'],
            'password' => $response['data']['data']['password'],
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
        $response = _CQ9Controller::init("gameboy/player/balance/" . $member_account->account,  [
            "account" => $member_account->account,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['balance'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return false;
        }

        // $x = SELF::account_deposit($member_account, $transfer);
        return SELF::account_deposit($member_account, $transfer);
    }

    public static function account_deposit($member_account, $transfer)
    {
        $params = [
            "account" => $member_account->account,
            "mtcode" => $transfer->uuid,
            "amount" => $transfer->amount,
        ];

        $response = _CQ9Controller::init("gameboy/player/deposit", $params);

        if (!$response['status']) {
            return false;
        }

        // $x = $response['data']['status'];
        return $response['data']['status'];
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
        $params = [
            "account" => $member_account->account,
            "mtcode" => $transfer->uuid,
            "amount" => $transfer->amount,
        ];

        $response = _CQ9Controller::init("gameboy/player/withdraw", $params);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['status'];
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

        $response = _CQ9Controller::init("gameboy/transaction/record/" . $transfer->uuid, []);

        if (!$response['data']['status']) {
            return false;
        }

        $transactionList = isset($response['data']['list']) ? $response['data']['list'] : [];

        foreach ($transactionList as $transaction) {
            if ($transaction['OrderId'] === $uuid) {
                return [
                    'status' => Transfer::STATUS_SUCCESS,
                    'remark' => $response['message'],
                ];
            }
        }

        return [
            'status' => Transfer::STATUS_FAIL,
            'remark' => $response['message'],
        ];
    }

    public static function launch(Member $member)
    {
        $member_account = SELF::check($member);

        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $response = _CQ9Controller::init("gameboy/player/login", [
            "account" => $member_account->username,
            'password' =>  $member_account->password,
        ]);

        if ($response['data']['status']['code'] !== '0') {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        $response = _CQ9Controller::init("gameboy/player/lobbylink", [
            'usertoken' => $response['data']['data']['usertoken'],
        ]);

        if ($response['data']['status']['code'] !== '0') {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['data']['url'],
        ];
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getBets($starttime, $endtime, $page = 1)
    {

        $response = _CQ9Controller::init("gameboy/order/view?" . "starttime=" . $starttime . "&" . "endtime=" . $endtime . "&" . "page=" . $page, [
            "starttime" => $starttime,
            "endtime" => $endtime,
            "page" => $page,
        ]);

        if (!$response['status']) {
            return false;
        }

        if (($response['data']['data']['TotalSize']) > 0 &&  ($response['data']['data']['TotalSize'] / 500 > $page)) {
            usleep(100000);
            return array_merge($response['data']['data']['Data'], SELF::getBets($starttime, $endtime, $page + 1));
        }

        return $response['data']['data']['Data'];
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
