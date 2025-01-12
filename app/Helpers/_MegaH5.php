<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_MegaH5Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class _MegaH5
{

    public static function create(Member $member)
    {
        $response = _MegaH5Controller::init("CreatePlayer", [
            'OperatorId' => config('api.MEGAH5_AGENT'),
            'RequestDateTime' => Carbon::now('UTC')->format('YmdHis'),
            'PlayerId' => $member->code,
        ]);

        if (!isset($response) || !is_array($response) || !isset($response['status'])) {
            return false;
        }


        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => $member->code,
            'password' => "Abcd" . rand(1000, 9999),
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
        $response = _MegaH5Controller::init("CheckBalance", [
            "OperatorId" => config('api.MEGAH5_AGENT'),
            'RequestDateTime' => Carbon::now('UTC')->format('YmdHis'),
            'PlayerId' => $member_account->username,
        ]);
        if (!$response['status']) {
            return false;
        }

        return $response['data']['CurrentBalance'];
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
        $referenceId = SELF::getBizID();
        $response = _MegaH5Controller::init("Deposit", [
            "OperatorId" => config('api.MEGAH5_AGENT'),
            "RequestDateTime" => Carbon::now('UTC')->format('YmdHis'),
            'PlayerId' => $member_account->username,
            'Amount' => $transfer->amount,
            'ReferenceId' => $referenceId,
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
        $referenceId = SELF::getBizID();
        $response = _MegaH5Controller::init("Withdraw", [
            "OperatorId" => config('api.MEGAH5_AGENT'),
            "RequestDateTime" => Carbon::now('UTC')->format('YmdHis'),
            'PlayerId' => $member_account->username,
            'Amount' => $transfer->amount,
            'ReferenceId' => $referenceId,
        ]);

        if (!$response['status']) {
            return false;
        }

        return true;
    }

    // public static function checkTransaction($uuid)
    // {
    //     $transfer =  Transfer::where('uuid', $uuid)->first();
    //     if (!$transfer) {
    //         return false;
    //     }

    //     $member_account = SELF::check($transfer->member);
    //     if (!$member_account) {
    //         return false;
    //     }

    //     $response = _MegaH5Controller::init("GetTransactionDetails", [
    //         "OperatorId" => config('api.MEGAH5_AGENT'),
    //         "RequestDateTime" => Carbon::now('UTC')->format('YmdHis'),
    //         'TranId' => 662591853475924020,
    //     ]);
    //     if (!$response['status']) {
    //         return false;
    //     }
    //     return [
    //         'url' => $response['data']['Url']
    //     ];

    // if (!$response['status'] || !$response['data']) {
    //     return false;
    // }

    // if (!isset($response['data'])) {
    //     return false;
    // }

    // if (!isset($response['data']['items'])) {
    //     return false;
    // return true;

    // foreach ($response['data']['items'] as $item) {
    //     if ($item['bizId'] == $transfer->id) {

    //         return [
    //             'status' => Transfer::STATUS_SUCCESS,
    //             'remark' => $response['status_message'],
    //         ];
    //     }
    // }

    // return [
    //     'status' => Transfer::STATUS_FAIL,
    //     'remark' => $response['status_message'],
    // ];
    // }

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
        $response = _MegaH5Controller::init("GameLogin", [
            "OperatorId" => config('api.MEGAH5_AGENT'),
            "RequestDateTime" => Carbon::now('UTC')->format('YmdHis'),
            'PlayerId' => $member_account->username,
            'Ip' => '175.41.155.108',
            'GameCode' => 0,
            'Currency' => 'MYR',
        ]);

        if (!$response['status']) {
            return false;
        }
        return [
            'url' => $response['data']['Url']
        ];
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getBets($date)
    {
        $response = _MegaH5Controller::init("PullLog", [
            "OperatorId" => config('api.MEGAH5_AGENT'),
            "RequestDateTime" => Carbon::now('UTC')->format('YmdHis'),
        ]);
        if (!$response || !$response['status']) {
            return false;
        }
        return $response['data']['Logs'] ?? [];
    }
    public static function flagLogs(array $transactionIds)
    {
        if (empty($transactionIds)) {
            Log::warning('No transaction IDs to flag.');
            return false;
        }

        $response = _MegaH5Controller::init("FlagLog", [
            "OperatorId" => config('api.MEGAH5_AGENT'),
            "RequestDateTime" => Carbon::now('UTC')->format('YmdHis'),
            "TransactionIds" => $transactionIds,
        ]);

        if (!$response || !isset($response['status']) || !$response['status']) {
            Log::error('Failed to flag logs:', ['response' => $response]);
            return false;
        }

        return $response;
    }
    public static function checkBets($date)
    {
        $logs = self::getBets($date);
        if (!is_array($logs)) {
            return [];
        }
        $transactionIds = array_filter(array_column($logs, 'TranId'));

        if (empty($transactionIds)) {
            return [];
        }
        if (self::flagLogs($transactionIds) === false) {
            return [];
        }

        return $logs;
    }




    public static function getBizID()
    {
        $snowflake = new \Godruoyi\Snowflake\Snowflake();
        return $snowflake->id();
    }
}
