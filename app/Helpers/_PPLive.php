<?php

namespace App\Helpers;

use App\Models\GameLogKey;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_PPLiveController;
use App\Models\Game;
use Carbon\Carbon;

class _PPLive
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        $response = _PPLiveController::init("/player/account/create/", [
            'secureLogin' => config('api.PPLIVE_SECURELOGIN'),
            'externalPlayerId' => $member->code,
            'currency' => "MYR",
        ]);

        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $response['data']['playerId'],
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
        $response = _PPLiveController::init("/balance/current/", [
            'secureLogin' => config('api.PPLIVE_SECURELOGIN'),
            'externalPlayerId' => $member_account->username,
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
        $response = _PPLiveController::init("/balance/transfer/", [
            'secureLogin' => config('api.PPLIVE_SECURELOGIN'),
            'externalPlayerId' => $member_account->username,
            'externalTransactionId' => $transfer->uuid,
            'amount' => $transfer->amount,
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
        $response = _PPLiveController::init("/balance/transfer/", [
            'secureLogin' => config('api.PPLIVE_SECURELOGIN'),
            'externalPlayerId' => $member_account->username,
            'externalTransactionId' => $transfer->uuid,
            'amount' => $transfer->amount * -1,
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

        $response = _PPLiveController::init("/balance/transfer/transactions", [
            'secureLogin' => config('api.PPLIVE_SECURELOGIN'),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['txStatus'] == 0) {
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
        $response = _PPLiveController::init("/getCasinoGames", [
            'secureLogin' => config('api.PPLIVE_SECURELOGIN'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);

        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $response = _PPLiveController::init("/game/start/", [
            'secureLogin' => config('api.PPLIVE_SECURELOGIN'),
            'externalPlayerId' => $member_account->username,
            'gameID' => 114, // 114 is the game id for the asia games lobby
            'language' => _PPLiveController::getLocale(),
        ]);
        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }
        return [
            'url' => $response['data']['gameURL'],
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBetsMember($timestamp)
    {
        $response = _PPLiveController::init("/DataFeeds/gamerounds/", [
            'login' => config('api.PPLIVE_SECURELOGIN'),
            'password' => config('api.PPLIVE_SECRET_KEY'),
            'timepoint' => $timestamp,
            'dataType' => 'LC',
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
