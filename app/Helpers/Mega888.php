<?php

namespace App\Helpers;

use App\Jobs\ProcessMegaInsertBetLog;
use App\Jobs\ProcessMegaSummaryBetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_MG88Controller;
use Carbon\Carbon;

class Mega888
{
    public $operator;
    public $product;
    public $agentLoginId;
    public $secretCode;
    public $sn;

    public static function create(Member $member)
    {
        $response = _MG88Controller::init("open.mega.user.create", [
            'agentLoginId' => config('api.MEGA_AGENT_LOGIN_ID'),
            'sn' => config('api.MEGA_SN'),
            'secretCode' => config('api.MEGA_SECRET_CODE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        usleep(100000);
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
                'username' => $response['data']['loginId'],
                'password' => "Abcd" . rand(1000, 9999),
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
        $response = _MG88Controller::init("open.mega.balance.get", [
            "loginId" => $member_account->username,
            'agentLoginId' => config('api.MEGA_AGENT_LOGIN_ID'),
            'sn' => config('api.MEGA_SN'),
            'secretCode' => config('api.MEGA_SECRET_CODE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
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
        $response = _MG88Controller::init("open.mega.balance.transfer", [
            "loginId" => $member_account->username,
            "amount" => $transfer->amount,
            "bizId" => $biz_id,
            'agentLoginId' => config('api.MEGA_AGENT_LOGIN_ID'),
            'sn' => config('api.MEGA_SN'),
            'secretCode' => config('api.MEGA_SECRET_CODE'),
        ]);
        if (!$response['status']) {
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
        $response = _MG88Controller::init("open.mega.balance.transfer", [
            "loginId" => $member_account->username,
            "amount" => $transfer->amount * -1,
            "bizId" => $biz_id,

            'agentLoginId' => config('api.MEGA_AGENT_LOGIN_ID'),
            'sn' => config('api.MEGA_SN'),
            'secretCode' => config('api.MEGA_SECRET_CODE'),
        ]);

        if (!$response['status']) {
            return false;
        }

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

        $response = _MG88Controller::init("open.mega.balance.transfer.query", [
            "loginId" => $member_account->username,
            "bizId" => $transfer->unique_id,
            'agentLoginId' => config('api.MEGA_AGENT_LOGIN_ID'),
            'sn' => config('api.MEGA_SN'),
            'secretCode' => config('api.MEGA_SECRET_CODE'),
        ]);

        if (!$response['status'] || !$response['data']) {
            return false;
        }

        if (!isset($response['data'])) {
            return false;
        }

        if (!isset($response['data']['items'])) {
            return false;
        }

        foreach ($response['data']['items'] as $item) {
            if ($item['bizId'] == $transfer->id) {

                return [
                    'status' => Transfer::STATUS_SUCCESS,
                    'remark' => $response['status_message'],
                ];
            }
        }

        return [
            'status' => Transfer::STATUS_FAIL,
            'remark' => $response['status_message'],
        ];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
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

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getBets($startDate, $endDate, $loginId, $page = 1)
    {
        $response = _MG88Controller::init("open.mega.game.order.page", [
            "sn" => config('api.MEGA_SN'),
            "loginId" => $loginId,
            "startTime" => $startDate,
            "endTime" => $endDate,
            "pageIndex" => $page,
            'secretCode' => config('api.MEGA_SECRET_CODE'),
        ]);

        if (!$response || !$response['status']) {
            return [];
        }

        $chunks = array_chunk($response['data']['items'], 150);
        foreach ($chunks as $chunk) {
            ProcessMegaInsertBetLog::dispatch($chunk, $loginId)->onQueue('insert_bet_logs');
        }

        if ($response['data']['totalPage'] > $page) {
            SELF::getBets($startDate, $endDate, $loginId, $page + 1);
        }
    }

    public static function getBetsMember($startTime, $endTime)
    {
        $response = _MG88Controller::init("open.mega.player.total.report", [
            "sn" => config('api.MEGA_SN'),
            'agentLoginId' => config('api.MEGA_AGENT_LOGIN_ID'),
            "startTime" => $startTime,
            "endTime" => $endTime,
        ]);

        if (!$response || !$response['status']) {
            return [];
        }

        return $response['data'];
    }

    public static function getBizID()
    {
        $snowflake = new \Godruoyi\Snowflake\Snowflake();
        return $snowflake->id();
    }
}
