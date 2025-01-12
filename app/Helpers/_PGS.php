<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_PGSController;


class _PGS
{
    public static function create(Member $member)
    {
        $response = _PGSController::init("player", [
            'playerName' => $member->code,
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            "merchantID" => config('api.PGS_MERCHANT_ID_LIVE'),
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
            'password' => $member->code,
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
        $account = SELF::check($member);
        return $account;
    }

    public static function balance($member)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _PGSController::init("wallet/amount", [
            'playerName' => $member_account->username,
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            'merchantID' => config('api.PGS_MERCHANT_ID_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['amount'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _PGSController::init("wallet/transfer-in", [
            'amount' => $transfer->amount,
            'merchantOrderNo' => $transfer->uuid,
            'playerName' => $member_account->username,
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            'merchantID' => config('api.PGS_MERCHANT_ID_LIVE'),
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

        $response = _PGSController::init("wallet/transfer-out", [
            'amount' => $transfer->amount,
            'merchantOrderNo' => $transfer->uuid,
            'playerName' => $member_account->username,
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            'merchantID' => config('api.PGS_MERCHANT_ID_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _PGSController::init("wallet/amount", [
            "playerName" => $member_account->username,
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            "merchantID" => config('api.PGS_MERCHANT_ID_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['amount'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _PGSController::init("wallet/transfer-out", [
            'amount' => $transfer->amount,
            'merchantOrderNo' => $transfer->uuid,
            'playerName' => $member_account->username,
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            'merchantID' => config('api.PGS_MERCHANT_ID_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _PGSController::init("wallet/transfer-in", [
            'amount' => $transfer->amount,
            'merchantOrderNo' => $transfer->uuid,
            'playerName' => $member_account->username,
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            'merchantID' => config('api.PGS_MERCHANT_ID_LIVE'),
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

  
        $response = _PGSController::init("transfer", [
            "merchantTransactionNo" => $transfer->uuid,
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            "merchantID" => config('api.PGS_MERCHANT_ID_LIVE'),
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

    public static function getGameList(){
        $response = _PGSController::init("games", [
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            "merchantID" => config('api.PGS_MERCHANT_ID_LIVE'),
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

        $game = Game::where('code', $gameid)->first();
        if (!$game) {
            return false;
        }
        $response = _PGSController::init("game/link", [
            "playerName" => $member_account->username,
            'merchantID' => config('api.PGS_MERCHANT_ID_LIVE'),
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            'lang' => _PGSController::getLocale(),
            'gameID' => $gameid,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }
        
        return [
            'url' => $response['data']['link']
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        
        
        $response = _PGSController::init("game-records", [
            'merchantSecretKey' => config('api.PGS_MERCHANT_KEY_LIVE'),
            "merchantID" => config('api.PGS_MERCHANT_ID_LIVE'),
            "startTime" => $startDate,
            "endTime" => $endDate,
            "pageSize" => 10000,
            "pageIndex" => $page,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['totalPage'] > 1 && $page < $response['data']['totalPage']) {
            return array_merge($response['data']['Data'], SELF::getBets($startDate, $endDate, $page + 1));
        }

        return $response['data']['items'];
    }
}
