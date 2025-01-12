<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_VpowerController;

class _Vpower
{
    public static function create(Member $member)
    {
        $password = self::randomPassword();

        $response = _VpowerController::init("getacc", [
            'Timestamp' => self::getTimestamp(),
            'Username' => $member->code,
            "AppId" => config('api.VPOWER_APP_ID_LIVE'),
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
            return self::create($member);
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
        $member_account = self::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _VpowerController::init("getbal", [
            'Timestamp' => self::getTimestamp(),
            'Username' => $member_account->username,
            "AppId" => config('api.VPOWER_APP_ID_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Credit'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = self::account($member);
        if (!$member_account) {
            return false;
        }

        $requestId = $member_account->username . self::getTimestamp() . random_int(1000, 9999);
        Transfer::where('uuid', $transfer->uuid)->update([
            'uuid' => $requestId,
        ]);

        $response = _VpowerController::init("creditxf", [
            'RequestID' => $requestId,
            'Timestamp' => self::getTimestamp(),
            'Username' => $member_account->username,
            'AppId' => config('api.VPOWER_APP_ID_LIVE'),
            "Amount" => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = self::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _VpowerController::init("signout", [
            'Timestamp' => SELF::getTimestamp(),
            'Username' => $member_account->username,
            'AppId' => config('api.VPOWER_APP_ID_LIVE')
        ]);

        if (!$response['status']) {
            return false;
        }

        $requestId = $member_account->username . self::getTimestamp() . random_int(1000, 9999);
        Transfer::where('uuid', $transfer->uuid)->update([
            'uuid' => $requestId,
        ]);

        $response = _VpowerController::init("creditxf", [
            'RequestID' => $requestId,
            'Timestamp' => self::getTimestamp(),
            'Username' => $member_account->username,
            'AppId' => config('api.VPOWER_APP_ID_LIVE'),
            "Amount" => $transfer->amount * -1,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _VpowerController::init("getbal", [
            'Timestamp' => self::getTimestamp(),
            'Username' => $member_account->username,
            "AppId" => config('api.VPOWER_APP_ID_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Credit'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $requestId = $member_account->username . self::getTimestamp() . random_int(1000, 9999);
        Transfer::where('uuid', $transfer->uuid)->update([
            'uuid' => $requestId,
        ]);

        $response = _VpowerController::init("signout", [
            'Timestamp' => SELF::getTimestamp(),
            'Username' => $member_account->username,
            'AppId' => config('api.VPOWER_APP_ID_LIVE')
        ]);

        if (!$response['status']) {
            return false;
        }

        $response = _VpowerController::init("creditxf", [
            'RequestID' => $requestId,
            'Timestamp' => self::getTimestamp(),
            'Username' => $member_account->username,
            'AppId' => config('api.VPOWER_APP_ID_LIVE'),
            "Amount" => $transfer->amount * -1,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $requestId = $member_account->username . self::getTimestamp() . random_int(1000, 9999);
        Transfer::where('uuid', $transfer->uuid)->update([
            'uuid' => $requestId,
        ]);

        $response = _VpowerController::init("creditxf", [
            'RequestID' => $requestId,
            'Timestamp' => self::getTimestamp(),
            'Username' => $member_account->username,
            'AppId' => config('api.VPOWER_APP_ID_LIVE'),
            "Amount" => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function checkTransaction($uuid)
    {
        $transfer = Transfer::where('uuid', $uuid)->first();
        if (!$transfer) {
            return false;
        }

        $member_account = self::check($transfer->member);
        if (!$member_account) {
            return false;
        }

        $response = _VpowerController::init("creditcheck", [
            'RequestID' => $transfer->uuid,
            "AppId" => config('api.VPOWER_APP_ID_LIVE'),
            'Timestamp' => self::getTimestamp(),
        ]);

        if (!$response['status']) {
            return false;
        }
        if ($response['data']['List'][0]['state'] == '2') {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => json_encode($response),
            ];
        }

        if ($response['data']['List'][0]['state'] == '0') {
            return [
                'status' => Transfer::STATUS_IN_PROGRESS,
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
        $response = _VpowerController::init("gamelist", [
            'Timestamp' => self::getTimestamp(),
            "AppId" => config('api.VPOWER_APP_ID_LIVE'),
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

        $response = _VpowerController::init("tkreq", [
            "Username" => $member_account->username,
            'Timestamp' => self::getTimestamp(),
            "AppId" => config('api.VPOWER_APP_ID_LIVE'),
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => config('api.VPOWER_GAME_LINK_LIVE') . "?id=" . $gameid . "&token=" . $response['data']['Token'] . "&back=" . base64_encode(route('home')) . "&lang=" . _VpowerController::getLocale()
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        $response = _VpowerController::init("glog", [
            "PageIndex" => $page,
            'Timestamp' => self::getTimestamp(),
            "BeginTime" => $startDate,
            "EndTime" => $endDate,
            "AppId" => config('api.VPOWER_APP_ID_LIVE'),
        ]);


        if (!$response['status']) {
            return [];
        }

        if (isset($response['data']['Data']) && count($response['data']['Data']) > 0 && $response['data']['Total'] / 200 > $page) {
            usleep(100000);
            return array_merge($response['data']['Data'], SELF::getBets($startDate, $endDate, $page + 1));
        }

        usleep(100000);
        return $response['data']['Data'] ?? [];
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