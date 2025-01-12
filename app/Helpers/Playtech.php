<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_PlaytechController;
use Illuminate\Support\Facades\Log;

class Playtech
{
    public static function create(Member $member)
    {
        if (!$member->product) {
            return false;
        }

        $playername = config('api.PT_PREFIX') . "_" . strtoupper($member->product->getSlug() . $member->code);
        $password = "Abcd" . rand(1000, 9999);

        $response = _PlaytechController::init("player/create", [
            "playername" => $playername,
            "adminname" => config('api.PT_ADMIN_NAME'),
            "entityname" => config('api.PT_ENTITY_NAME'),
            "password" => $password,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);

        if (!$response['status'] && $response['code'] != 19) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => $playername,
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
        $account = SELF::check($member);
        return $account;
    }

    public static function balance($member)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _PlaytechController::init("player/balance", [
            "playername" => $member_account->username,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);


        if (!$response['status']) {
            return false;
        }

        return $response['data']['balance'];
    }

    public static function account_balance($member_account)
    {
        $response = _PlaytechController::init("player/balance", [
            "playername" => $member_account->username,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);


        if (!$response['status']) {
            return false;
        }

        return $response['data']['balance'];
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _PlaytechController::init("player/withdraw", [
            "playername" => $member_account->username,
            "amount" => $transfer->amount,
            "adminname" => config('api.PT_ADMIN_NAME'),
            "externaltranid" => $transfer->uuid,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['data']['result'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _PlaytechController::init("player/deposit", [
            "playername" => $member_account->username,
            "amount" => $transfer->amount,
            "adminname" => config('api.PT_ADMIN_NAME'),
            "externaltranid" => $transfer->uuid,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['data']['result'];
    }
    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _PlaytechController::init("player/withdraw", [
            "playername" => $member_account->username,
            "amount" => $transfer->amount,
            "adminname" => config('api.PT_ADMIN_NAME'),
            "externaltranid" => $transfer->uuid,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['data']['result'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _PlaytechController::init("player/deposit", [
            "playername" => $member_account->username,
            "amount" => $transfer->amount,
            "adminname" => config('api.PT_ADMIN_NAME'),
            "externaltranid" => $transfer->uuid,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['data']['result'];
    }


    public static function checkTransaction($uuid)
    {
        $transfer =  Transfer::where('uuid', $uuid)->first();
        if (!$transfer) {
            return false;
        }

        $response = _PlaytechController::init("player/checktransaction", [
            "externaltransactionid" => $uuid,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['message'],
            ];
        }

        if ($response['data']['status'] == "approved") {
            return [
                'status' => Transfer::STATUS_SUCCESS,
                'remark' => $response['message'],
            ];
        }

        if ($response['data']['status'] == "declined") {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['message'],
            ];
        }

        if ($response['data']['status'] == "missing") {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['message'],
            ];
        }

        if ($response['data']['status'] == "waiting") {
            return [
                'status' => Transfer::STATUS_IN_PROGRESS,
                'remark' => $response['message'],
            ];
        }

        if ($response['data']['status'] == "notallowed") {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['message'],
            ];
        }
        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => $response['data']['msg'],
        ];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        if ($member->product && $gameid == null) {
            $client = $isMobile ? "live_mob" : "live_desk";
            return [
                'url' => route('lobby.launch', [
                    'product' => $member->product,
                    'username' => $member_account->username,
                    'password' => $member_account->password,
                    'client' => $client,
                    'gameid' => null,
                    'lang' => SELF::getLocale(),
                ]),
            ];
        }

        $client = $isMobile ? "ngm_mobile" : "ngm_desktop";
        $game = Game::has('product')->where('code', $gameid)->first();
        if (!$game) {
            return false;
        }

        return [
            'url' => route('lobby.launch', [
                'product' => $member->product,
                'username' => $member_account->username,
                'password' => $member_account->password,
                'client' => $client,
                'gameid' => $game->code,
                'lang' => SELF::getLocale(),
            ]),
        ];
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        $response = _PlaytechController::init("game/flow", [
            "startdate" => $startDate,
            "enddate" => $endDate,
            "page" => $page,
            "api_key" => config('api.PT_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($page < $response['pagination']['totalPages']) {
            return array_merge($response['data'], SELF::getBets($startDate, $endDate, $page + 1));
        }

        return $response['data'];
    }

    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "zh-CN";
        }
        return "en";
    }
}
