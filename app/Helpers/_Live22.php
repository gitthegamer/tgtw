<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\GameLogKey;
use App\Models\Member;
use App\Models\Transfer;
use App\Modules\_Live22Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class _Live22
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        $response = _Live22Controller::init("CreatePlayer", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'PlayerId' => $member->code,
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
        $account = SELF::check($member);
        return $account;
    }

    public static function balance($member)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _Live22Controller::init("CheckBalance", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'PlayerId' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['status'] == 200) {
            return $response['data']['CurrentBalance'];
        }

        return false;
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _Live22Controller::init("Deposit", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'PlayerId' => $member_account->username,
            'ReferenceId' => $transfer->uuid,
            'Amount' => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['status'] == 200) {
            return true;
        }

        return true;
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _Live22Controller::init("Withdraw", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'PlayerId' => $member_account->username,
            'ReferenceId' => $transfer->uuid,
            'Amount' => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['status'] == 200) {
            return true;
        }

        return false;
    }

    public static function account_balance($member_account)
    {
        $response = _Live22Controller::init("CheckBalance", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'PlayerId' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['status'] == 200) {
            return $response['data']['CurrentBalance'];
        }

        return false;
    }

    public static function account_deposit($member_account, $transfer)
    {
        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _Live22Controller::init("Deposit", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'PlayerId' => $member_account->username,
            'ReferenceId' => $transfer->uuid,
            'Amount' => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['status'] == 200) {
            return true;
        }

        return true;
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _Live22Controller::init("Withdraw", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'PlayerId' => $member_account->username,
            'ReferenceId' => $transfer->uuid,
            'Amount' => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['status'] == 200) {
            return true;
        }

        return false;
    }

    public static function getGameList()
    {
        $response = _Live22Controller::init("GetGameList", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['status'] == 200) {
            return $response['data']['Game'];
        }

        return false;
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

        $current_balance = $member_account->balance();

        if (($transfer->before_balance === 0) && ($current_balance === 0)) {
            return [
                'status' => Transfer::STATUS_SUCCESS,
                'remark' => 'manual check',
            ];
        }

        if ($transfer->before_balance === $current_balance) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => 'manual check',
            ];
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => 'manual check',
        ];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $member_account = SELF::check($member);
        if (!$member_account) {
            return false;
        }

        $currentRequest = request();
        $playerIp = $currentRequest->header('X-Forwarded-For', $currentRequest->ip());

        $response = _Live22Controller::init("GameLogin", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'PlayerId' => $member_account->username,
            'Ip' => $playerIp,
            'GameCode' => $gameid,
            'Currency' => 'MYR',
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['status'] == 200) {
            return [
                'url' => $response['data']['Url']
            ];
        }

        return false;
    }

    public static function getBets()
    {
        $response = _Live22Controller::init("PullLog", [
            'OperatorId' => config('api.LIVE22_OPERATOR_ID'),
            'RequestDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        Log::channel('live22_api_records')->debug("check: ".json_encode($response));

        if (!$response['status']) {
            return false;
        }
        
        if ($response['status'] == 200) {
            return $response['data']['Logs'];
        }

        return false;
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
