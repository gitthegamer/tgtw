<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_CrowdPlayController;
use Illuminate\Support\Facades\Log;

class _CrowdPlay
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $response = _CrowdPlayController::init("api/user/create", [
            'host_id' => config('api.CROWDPLAY_HOST_ID'),
            'member_id' => $member->code,
            'currency' => 'MYR',
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
        $response = _CrowdPlayController::init("api/user/balance", [
            'host_id' => config('api.CROWDPLAY_HOST_ID'),
            'member_id' => $member_account->username,
        ]);
        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['balance'] / 100 ?? 0;
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
        $response = _CrowdPlayController::init("api/user/deposit-v2", [
            'host_id' => config('api.CROWDPLAY_HOST_ID'),
            'member_id' => $member_account->username,
            'amount' => $transfer->amount * 100,
            'transid' => $transfer->uuid
        ]);

        if (!$response['status']) {
            return false;
        }
        $transfer->uuid = $response["data"]["data"]["vg_transaction_id"];
        $transfer->save();
        return true;
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
        $response = _CrowdPlayController::init("api/user/withdraw-v2", [
            'host_id' => config('api.CROWDPLAY_HOST_ID'),
            'member_id' => $member_account->username,
            'amount' => $transfer->amount * 100,
            'transid' => $transfer->uuid
        ]);

        if (!$response['status']) {
            return false;
        }
        $transfer->uuid = $response["data"]["data"]["vg_transaction_id"];
        $transfer->save();
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

        $response = _CrowdPlayController::init("api/user/wallet-trans-status", [
            'host_id' => config('api.CROWDPLAY_HOST_ID'),
            'trans_id' => $uuid
        ]);

        Log::debug("Response: " . json_encode($response));
        if (!$response['status']) {
            [
                'status' => Transfer::STATUS_FAIL,
                'remark' => json_encode($response),
            ];
        }
        if ($response['status']) {
            return [
                'status' => Transfer::STATUS_SUCCESS,
                'remark' => json_encode($response),
            ];
        }

        return [
            'status' => Transfer::STATUS_FAIL,
            'remark' => json_encode($response),
        ];
    }

    public static function getGameList()
    {
        return true;
    }


    public static function launch(Member $member, $gameid = null)
    {
        $member_account = SELF::check($member);

        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $response = _CrowdPlayController::init("api/user/generate-access-token", [
            'host_id' => config('api.CROWDPLAY_HOST_ID'),
            'member_id' => $member_account->username,
        ]);
        if (!$response || !isset($response['data']['data']) || !isset($response['data']['data']['status_code']) || $response['data']['data']['status_code'] !== 0) {
            Log::debug("2");
            return false;
        }

        $access_token = $response['data']['data']['access_token'] ?? 0;
        return [
            'url' => config('api.CROWDPLAY_LOBBY_LINK') . '?host_id=' . config('api.CROWDPLAY_HOST_ID') . '&access_token=' . $access_token
        ];
    }

    public static function getBets($version_key = null, $isSkipStore = false)
    {
        if (!$version_key && !$isSkipStore) {
            $version_key = cache()->get('crowdplay_version_key', 0);
        }

        echo $version_key;

        $response = _CrowdPlayController::init("api/report", [
            'host_id' => config('api.CROWDPLAY_HOST_ID'),
            'key' => $version_key,
            'page_size' => 500
        ]);

        if (!$response['status']) {
            return false;
        }

        if (isset($response['data']['data']['key']) && $response['data']['data']['key'] != 0) {
            cache()->put(
                'crowdplay_version_key',
                $response['data']['data']['key']
            );
        }

        return [
            "data" => $response['data']['data']['report'],
            "key" => $version_key
        ];
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

    public static function getBizID()
    {
        $snowflake = new \Godruoyi\Snowflake\Snowflake();
        return $snowflake->id();
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
