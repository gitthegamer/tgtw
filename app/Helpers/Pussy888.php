<?php

namespace App\Helpers;

use App\Jobs\ProcessPussyInsertBetLog;
use App\Jobs\ProcessPussySummaryBetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_Pussy888Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Pussy888
{
    public static function create(Member $member)
    {
        $response = _Pussy888Controller::init("ashx/account/account.ashx?action=RandomUserName", [
            "userName" => config('api.PUSSY_AGENT_NAME'),
            'agentName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        $response = _Pussy888Controller::init("ashx/account/account.ashx?action=addUser", [
            'userName' => $username = $response['data']['account'],
            'password' => $password = "Abcd" . rand(1000, 9999),

            'agentName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

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
        $response = _Pussy888Controller::init("ashx/account/account.ashx?action=getUserInfo", [
            'userName' => $member_account->username,
            'agentName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
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
        $response = _Pussy888Controller::init("ashx/account/setScore.ashx", [
            "orderid" => $transfer->uuid,
            "scoreNum" => $transfer->amount,
            "userName" => $member_account->username,
            "ActionUser" => config('api.PUSSY_AGENT_NAME'),
            'agentName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
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
        $response = _Pussy888Controller::init("ashx/account/setScore.ashx", [
            "orderid" => $transfer->uuid,
            "scoreNum" => $transfer->amount * -1,
            "userName" => $member_account->username,
            "ActionUser" => config('api.PUSSY_AGENT_NAME'),

            'agentName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['success'];
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

        $response = _Pussy888Controller::init("ashx/UserscoreLog.ashx", [
            "userName" => $member_account->username,
            'sDate' => Carbon::parse($transfer->created_at)->subMinutes(15)->format('Y-m-d H:i:s'),
            'eDate' => Carbon::parse($transfer->created_at)->addMinutes(15)->format('Y-m-d H:i:s'),

            'agentName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        $transactionList = isset($response['data']['results']) ? $response['data']['results'] : [];

        foreach ($transactionList as $transaction) {
            if ($transaction['OrderId'] === $uuid) {
                return [
                    'status' => Transfer::STATUS_SUCCESS,
                    'remark' => $response['message'],
                ];
            }
        }

        return [
            'status' => Transfer::STATUS_FAIL,
            'remark' => isset($response['message']) ? $response['message'] : '查無交易紀錄',
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

    public function startGame(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        return true;
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function product_logs($startDate, $endDate, $userName, $page = 1)
    {
        $response = _Pussy888Controller::init("ashx/GameLog.ashx", [
            "pageIndex" => $page,
            "userName" => $userName,
            "sDate" => $startDate,
            "eDate" => $endDate,
            'agentName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return [];
        }

        $chunks = array_chunk($response['data']['results'], 150);
        foreach ($chunks as $chunk) {
            ProcessPussyInsertBetLog::dispatch($chunk, $userName)->onQueue('insert_bet_logs');
        }

        if (count($response['data']['results']) > 0 &&  $response['data']['total'] / 1000 > $page) {
            SELF::product_logs($startDate, $endDate, $userName, $page + 1);
        }
    }

    public static function get_player_list($startDate, $endDate)
    {
        $response = _Pussy888Controller::init("ashx/AgentTotalReport.ashx", [
            "sDate" => $startDate,
            "eDate" => $endDate,
            'userName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return [];
        }

        return $response['data']['results'];
    }

    public static function checkKioskBalance()
    {
        $response = _Pussy888Controller::init("ashx/account/account.ashx?action=getUserInfo", [
            'agentName' => config('api.PUSSY_AGENT_NAME'),
            'userName' => config('api.PUSSY_AGENT_NAME'),
            'authcode' => config('api.PUSSY_AUTH_CODE'),
            'secretkey' => config('api.PUSSY_SECRET_KEY'),
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data']['ScoreNum'];
    }
}
