<?php

namespace App\Helpers;

use App\Jobs\ProcessKissInsertBetLog;
use App\Jobs\ProcessKissSummaryBetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_918kissController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class _918kiss
{
    public static function create(Member $member)
    {
        $response = _918kissController::init("ashx/account/account.ashx?action=RandomUserName", [
            "userName" => config('api.KISS_AGENT_NAME'),
            'agentName' => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        $response = _918kissController::init("ashx/account/account.ashx?action=addUser", [
            'userName' => $username = $response['data']['account'],
            'password' => $password = "Abcd" . rand(1000, 9999),

            'agentName' => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        //check if memberaccount is exists
        $member_account = MemberAccount::where('member_id', $member->id)
            ->where('product_id', $member->product_id)
            ->first();

        if (!$member_account) {
            return $member->member_accounts()->updateOrCreate([
                'member_id' => $member->id,
                'product_id' => $member->product_id,
            ], [
                'account' => $member->code,
                'username' => $username,
                'password' => $password,
            ]);
        } else {
            return $member_account;
        }
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
        $response = _918kissController::init("ashx/account/account.ashx?action=getUserInfo", [
            'userName' => $member_account->username,
            'agentName' => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['ScoreNum'];
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
        $response = _918kissController::init("ashx/account/setScore.ashx", [
            "orderid" => $transfer->uuid,
            "scoreNum" => $transfer->amount,
            "userName" => $member_account->username,
            "ActionUser" => config('api.KISS_AGENT_NAME'),
            'agentName' => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['success'];
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
        $response = _918kissController::init("ashx/account/setScore.ashx", [
            "orderid" => $transfer->uuid,
            "scoreNum" => $transfer->amount * -1,
            "userName" => $member_account->username,
            "ActionUser" => config('api.KISS_AGENT_NAME'),

            'agentName' => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['success'];
    }

    public static function checkTransaction($uuid)
    {
        $response = _918kissController::init("ashx/getOrder.ashx", [
            "orderid" => $uuid,
            'agentName' => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['code'] == 0) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['data']['msg'],
            ];
        }


        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => $response['data']['msg'],
        ];
    }

    public static function launch($member, $game, $isMobile)
    {
        $member_account = SELF::check($member);

        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        // if (SELF::balance($member) === false) {
        //     return Product::ERROR_PROVIDER_MAINTENANCE;
        // }

        return [
            'member_account' => $member_account,
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function product_logs($startTime, $endTime, $userName, $page = 1)
    {
        $response = _918kissController::init("ashx/GameLog.ashx", [
            "pageIndex" => $page,
            "userName" => $userName,
            "sDate" => $startTime,
            "eDate" => $endTime,
            'agentName' => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        $chunks = array_chunk($response['data']['results'], 100);
        foreach ($chunks as $chunk) {
            ProcessKissInsertBetLog::dispatch($chunk, $userName)->onQueue('insert_bet_logs');
        }

        if (count($response['data']['results']) > 0 && $response['data']['total'] / 1000 > $page) {
            SELF::product_logs($startTime, $endTime, $userName, $page + 1);
        }
    }

    public static function get_player_list($sDate, $eDate)
    {
        $response = _918kissController::init("ashx/AgentTotalReport.ashx", [
            "sDate" => $sDate,
            "eDate" => $eDate,
            "userName" => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return [];
        }

        return isset($response['data']['results']) ? $response['data']['results'] : [];
    }

    public static function checkKioskBalance()
    {
        $response = _918kissController::init("ashx/account/account.ashx?action=getUserInfo", [
            "userName" => config('api.KISS_AGENT_NAME'),
            "agentName" => config('api.KISS_AGENT_NAME'),
            'authcode' => config('api.KISS_AUTH_CODE'),
            'secretkey' => config('api.KISS_SECRET_KEY'),
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data']['ScoreNum'];
    }
}
