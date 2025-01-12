<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_ACE333Controller;

class _ACE333
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword(8);
        $response = _ACE333Controller::init("api/createplayer", [
            'accountID' => $member->code,
            'nickname' => str_replace(' ', '', $member->username),
            'currency' => $member->currency,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $response['data']['playerID'],
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
        $response = _ACE333Controller::init("api/getbalance", [
            'currency' => 'MYR',
            'playerID' => $member_account->account,
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
        $response = _ACE333Controller::init("api/topup", [
            'playerID' => $member_account->account,
            'referenceID' => $transfer->uuid,
            'topUpAmount' => $transfer->amount,
            'currency' => 'MYR',
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
        $response = _ACE333Controller::init("api/LogOut", [
            'playerID' => $member_account->account,
            'currency' => $member_account->member->currency,
        ]);

        if (!$response['status']) {
            return false;
        }

        $response = _ACE333Controller::init("api/withdraw", [
            'playerID' => $member_account->account,
            'referenceID' => $transfer->uuid,
            'withdrawAmount' => $transfer->amount,
            'currency' => $member_account->member->currency,
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

        $response = _ACE333Controller::init("api/gettimepoint", [
            'datetime' => $transfer->created_at,
        ]);

        if (!$response['status']) {
            return false;
        }

        $response = _ACE333Controller::init("api/CheckOrder", [
            'currency' => 'MYR',
            'referenceID' => $transfer->uuid,
            'timepoint' => $response['data'],
        ]);

        if (!$response['status']) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['data']['referenceID'],
            ];
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => $response['data']['referenceID'],
        ];
    }

    public static function getGameList()
    {
        $data = (object)[
            'gameType' => _ACE333Controller::GAME_TYPE['Slot Game'],
        ];

        $loginToken = _ACE333Controller::encryptedString("api/gamelist", $data);

        $response = _ACE333Controller::init("api/gamelist", [
            'q' => $loginToken['q'],
            's' => $loginToken['s'],
            'accessToken' => config('api.ACE333_API_TOKEN'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
    }

    public static function launch($member, $isMobile)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $data = (object)[
            'nickName' => str_replace(' ', '', $member->username),
            'userName' => $member_account->username . config('api.ACE333_POST_FIX_ID'),
            'password' => $member_account->password,
            'currency' => $member->currency,
        ];

        $loginToken = _ACE333Controller::encryptedString("api/authenticate", $data);

        $response = _ACE333Controller::init("api/authenticate", [
            'q' => $loginToken['q'],
            's' => $loginToken['s'],
            'accessToken' => config('api.ACE333_API_TOKEN'),
        ]);


        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        $actk = $response['data']['actk'];

        return [
            'url' => config('api.ACE333_H5_LOBBY_URL') . "apiLobby?actk=" . $actk
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBets($date)
    {
        $response = _ACE333Controller::init("api/gettimepoint", [
            'datetime' => $date,
        ]);

        if (!$response['status']) {
            return false;
        }

        $response = _ACE333Controller::init("api/accounttransactions3", [
            'timepoint' => $response['data'],
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
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

        while (strlen($password) < $len) {
            $randomSet = $sets[array_rand($sets)];
            $password .= $randomSet[array_rand(str_split($randomSet))];
        }

        return str_shuffle($password);
    }
}
