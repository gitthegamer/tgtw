<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_NextSpinController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class _NextSpin
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $memberCode = str_replace(' ', '', $member->code);
        $memberCode = substr($memberCode, 0, 20);
        //deposit is also create player here but amount is 0
        $response = _NextSpinController::init("deposit", [
            'acctId' => $memberCode,
            'currency' => "MYR",
            'amount' => 0,
            'merchantCode' => config('api.NEXTSPIN_MERCHANTCODE'),
            'serialNo' => Str::uuid(), //unique
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
        $response = _NextSpinController::init("getAcctInfo", [
            'acctId' => $member_account->username,
            'merchantCode' => config('api.NEXTSPIN_MERCHANTCODE'),
            "serialNo" => Str::uuid(),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['list'][0]['balance'];
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
        $response = _NextSpinController::init("deposit", [
            'acctId' => $member_account->username,
            'currency' => "MYR",
            'amount' => $transfer->amount,
            'merchantCode' => config('api.NEXTSPIN_MERCHANTCODE'),
            'serialNo' => $transfer->uuid, //unique
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
        $response = _NextSpinController::init("withdraw", [
            'acctId' => $member_account->username,
            'currency' => "MYR",
            'amount' => $transfer->amount,
            'merchantCode' => config('api.NEXTSPIN_MERCHANTCODE'),
            'serialNo' => $transfer->uuid, //unique
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


        $response = _NextSpinController::init("checkTransfer", [
            'merchantCode' => config('api.NEXTSPIN_MERCHANTCODE'),
            'serialNo' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['status'] == 0) {
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

    public static function launch(Member $member, $isMobile = false)
    {
        $member_account = SELF::check($member);

        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        return [
            'url' => config('api.NEXTSPIN_GAME_LINK') . config('api.NEXTSPIN_MERCHANTCODE') . '/auth/?acctId=' . $member_account->username . '&language=' . _NextSpinController::getLocale() . '&token=t' . $member->token . '&channel=' . ($isMobile ? 'Mobile' : 'Web') . '&isLobby=true',
        ];
    }

    public static function getBets($startdate, $enddate, $page = 1)
    {
        $allBets = [];

        $response = _NextSpinController::init("getBetHistory", [
            'beginDate' => $startdate,
            'endDate' => $enddate,
            'pageIndex' => $page,
            'merchantCode' => config('api.NEXTSPIN_MERCHANTCODE'),
            'serialNo' => Str::uuid(),
        ]);

        if (!$response['status']) {
            return false;
        }

        if($response['data']['resultCount'] <= 0) {
            return [];
        }

        if($response['data']['resultCount'] > 0) {
            $allBets = array_merge($allBets, $response['data']['list']);
        }

        if ($response['data']['pageCount'] > 1 && $page < $response['data']['pageCount']) {
            $allBets = array_merge($allBets, SELF::getBets($startdate, $enddate, $page + 1));
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
}
