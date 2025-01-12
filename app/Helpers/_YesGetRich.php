<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Transfer;
use App\Modules\_YesGetRichController;
use Illuminate\Support\Facades\Log;

class _YesGetRich
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $response = _YesGetRichController::init("/CreateMember", [
            'Account' => $member->code,
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
        $response = _YesGetRichController::init("/GetMemberInfo", [
            'Accounts' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Data'][0]['Balance'];
    }
    public static function deposit($member, $transfer)
    {

        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }
        return SELF::account_deposit($member_account, $transfer);
    }
    public static function account_deposit($member_account, $transfer)
    {

        $response = _YesGetRichController::init("/Transfer", [
            'Account' => $member_account->username,
            'TransactionId' => $transfer->uuid,
            'Amount' => $transfer->amount,
            'TransferType' => 2,
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

        return SELF::account_withdrawal($member_account, $transfer);
    }


    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _YesGetRichController::init("/Transfer", [
            'Account' => $member_account->username,
            'TransactionId' => $transfer->uuid,
            'Amount' => $transfer->amount,
            'TransferType' => 1,
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

        $response = _YesGetRichController::init("/CheckTransfer", [
            'TransactionId' => $uuid,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['Data']['Status'] == 2) {
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

    public static function getGameList()
    {
        $response = _YesGetRichController::init("/GameList", []);

        if ($response['status'] == false) {
            return [];
        }

        return $response['data']['Data'];
    }


    public static function launch(Member $member, $gameid = null)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $member_account = SELF::check($member);

        if (!$member_account) {
            return false;
        }

        $response = _YesGetRichController::init("/Login", [
            'Account' => $member_account->username,
            'GameId' => $gameid,
            'Lang' => _YesGetRichController::getLocale(),
        ]);

        Log::debug(json_encode($response));
        if (!$response['status']) {
            return false;
        }
        return [
            'url' => $response['data']
        ];
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        $response = _YesGetRichController::init("/GetBetRecordByDateTime", [
            'StartTime' => $startDate,
            'EndTime' => $endDate,
            'Page' => $page,
            'PageLimit' => 999
        ]);

        if (!$response['status']) {
            return [];
        }

        if ($response['data']['Data']['Pagination']['TotalPages'] > 1 && $response['data']['Data']['Pagination']['TotalPages'] / 999 > $page) {
            return array_merge($response['data']['Data']['result'], self::getBets($startDate, $endDate, $page + 1));
        }

        return $response['data']['Data']['result'];
    }


    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getTimestamp()
    {
        $current_timestamp = time();
        $formatted_timestamp = date("U", $current_timestamp);
        return $formatted_timestamp;
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
