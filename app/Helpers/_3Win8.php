<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_3WIN8Controller;

class _3Win8
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        $response = _3WIN8Controller::init("user_register", [
            'agid' => config('api.WIN38_AGID'),
            'username' => $username = strtolower($member->code),
            'password' => $password,
            'lang' => _3WIN8Controller::getLocale(),
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $username,
            'username' => $username,
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
        $response = _3WIN8Controller::init("user_detail", [
            'agid' => config('api.WIN38_AGID'),
            'username' => $member_account->username
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return ($response['data']['balance']);
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
        $transfer->update(['unique_id' => $biz_id = SELF::getBizID()]);
        $response = _3WIN8Controller::init("user_transfer", [
            'agid' => config('api.WIN38_AGID'),
            'amount' => $transfer->amount,
            'username' =>  $member_account->username,
            'orderid' => $biz_id,
        ]);


        if ($response['status'] == false) {
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
        $transfer->update(['unique_id' => $biz_id = SELF::getBizID()]);
        $response = _3WIN8Controller::init("user_transfer", [
            'agid' => config('api.WIN38_AGID'),
            'amount' => $transfer->amount * -1,
            'username' =>  $member_account->username,
            'orderid' => $biz_id,
        ]);

        if ($response['status'] == false) {
            return false;
        }
        return true;
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

        $response = _3WIN8Controller::init("user_transfer_detail", [
            'agid' => config('api.WIN38_AGID'),
            'username' =>  $member_account->username,
            'orderid' => $transfer->unique_id,
        ]);

        if (!$response['status']) {
            if ($response['data']['error']['status_code'] == 1) {
                return [
                    'status' => Transfer::STATUS_FAIL,
                    'remark' => json_encode($response),
                ];
            }
            return false;
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => json_encode($response),
        ];
    }

    public static function getGameList()
    {
        $response = _3WIN8Controller::init("user_game_list", [
            'agid' => config('api.WIN38_AGID'),
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

        $response = _3WIN8Controller::init("user_play_game", [
            'agid' => config('api.WIN38_AGID'),
            'username' => $member_account->username,
            'game_code' => $gameid,
            'game_support' => $isMobile ? "" : "H5",
            'game_back_url' => config('api.MONEY_URL')
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['url']
        ];
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        $response = _3WIN8Controller::init("user_game_history", [
            'agid' => config('api.WIN38_AGID'),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        if (!$response['status']) {
            return false;
        }

        if(isset($response['data']['error_code'])){
            return [];
        }

        // if (count($response['data']['results']) > 0 && $response['data']['total'] / 1000 > $page) {
        //     usleep(100000);
        //     return array_merge($response['data']['results'], SELF::product_logs($date, $userName, $page + 1));
        // }

        return $response['data']['list'];
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

    public static function randomPhone($requiredLength = 7, $highestDigit = 7)
    {
        $sequence = '';
        for ($i = 0; $i < $requiredLength; ++$i) {
            $sequence .= mt_rand(0, $highestDigit);
        }
        $numberPrefixes = ['011', '012', '013', '014', '016', '017', '018', '019'];
        for ($i = 0; $i < 21; ++$i) {
            $phone = $numberPrefixes[array_rand($numberPrefixes)] . $sequence;
        }
        return $phone;
    }

    public static function getBizID()
    {
        $snowflake = new \Godruoyi\Snowflake\Snowflake();
        return $snowflake->id();
    }
}
