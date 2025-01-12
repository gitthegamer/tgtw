<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_AstarCasinoController;

class _AstarCasino
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $response = _AstarCasinoController::init("createUser", [
            'userName' => $member->code,
            'currency' => "MYR",
        ]);

        if (!$response['status']) {
            return false;
        }

        $member_account = $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => $member->code,
            'password' => $password,
        ]);

        SELF::bet_limit($member_account, $member);

        return $member_account;
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
        $response = _AstarCasinoController::init("getBalance", [
            'userName' => $member_account->account,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['value']['balance'];
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
        $response = _AstarCasinoController::init("deposit", [
            'userName' => $member_account->account,
            'amount' => $transfer->amount,
            'serial' => config('api.ASTAR_CHANNEL') . "deposit" . $transfer->uuid,
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
        $response = _AstarCasinoController::init("withdraw", [
            'userName' => $member_account->account,
            'amount' => $transfer->amount,
            'serial' => config('api.ASTAR_CHANNEL') . "withdraw" . $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }

        $response = _AstarCasinoController::init("logout", [
            'userName' => $member_account->account,
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

        $response = _AstarCasinoController::init("getTransList", [
            'startTime' =>  Carbon::now()->subMinutes(1)->format('Y-m-d H:i:s'),
            'endTime' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        if (!$response['status']) {
            return false;
        }

        if (!$response['data']['state'] == 0) {
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

        $response = _AstarCasinoController::init("loginWithChannel", [
            'userName' => $member_account->account,
            'language' => _AstarCasinoController::getLocale(),
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        SELF::bet_limit($member_account, $member);

        return [
            'url' => $response['data']['value'],
        ];
    }

    public static function getBets($startdate, $enddate, $page = 1)
    {
        $allBets = [];

        $response = _AstarCasinoController::init("getRecordByCondition", [
            'startTime' => $startdate,
            'endTime' => $enddate,
            'correctTime' => $startdate,
            'pageIndex' => $page,
            'version' => '3.1',
        ]);

        if (!$response['status']) {
            return false;
        }

        if (!$response['data']['state'] == 0 || $response['data']['value']['total'] < 1) {
            return [];
        }

        $bets = $response['data']['value']['gameRecords'];
        if (!empty($bets)) {
            $allBets = array_merge($allBets, $bets);
        }

        if ($response['data']['value']['total'] >= 200 && $page < $response['data']['value']['pageIndex']) {
            $allBets = array_merge($allBets, SELF::getBets($startdate, $enddate, $page + 1));
        }

        return $allBets;
    }


    public static function bet_limit($member_account, $member)
    {
        $bet_limit = $member->getBetLimit();
        if (!$bet_limit) {
            return;
        }

        $response = _AstarCasinoController::init("setUserLimit", [
            'userName' => $member_account->account,
            'limit' => $bet_limit['code'] ?? "C",
        ]);

        if (!$response['status']) {
            return false;
        }

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
