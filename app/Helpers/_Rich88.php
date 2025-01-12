<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_Rich88Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class _Rich88
{

    public static function create(Member $member)
    {
        $member_account = $member->member_accounts()->where('member_id', $member->id)->where('product_id', $member->product_id)->first();

        if (!$member_account) {
            $response = _Rich88Controller::init("Login", [
                'account' => $member->code,
            ]);

            if (!$response['status']) {
                return false;
            }

            $member_account = $member->member_accounts()->updateOrCreate([
                'member_id' => $member->id,
                'product_id' => $member->product_id,
            ], [
                'account' => $member->code,
                'username' => $member->code,
                'password' => "Abcd" . rand(1000, 9999),
            ]);
        }
        return $member_account;
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
        $response = _Rich88Controller::init("GetBalance", [
            'account' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['balance'] ?? null;
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
        $timestamp = time();
        $randomString = strtoupper(Str::random(3));
        $transfer_no = $timestamp . $randomString;
        $transfer->uuid = $transfer_no;
        $transfer->save();
        $response = _Rich88Controller::init("Transfer", [
            'account' => $member_account->username,
            'transfer_no' => $transfer_no,
            'transfer_type' => "0",
            'amount' => (float)$transfer->amount,
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
        $timestamp = time();
        $randomString = strtoupper(Str::random(3));
        $transfer_no = $timestamp . $randomString;
        $transfer->uuid = $transfer_no;
        $response = _Rich88Controller::init("Transfer", [
            'account' => $member_account->username,
            'transfer_no' => $transfer_no,
            'transfer_type' => "1",
            'amount' => (float)$transfer->amount,
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
        $response = _Rich88Controller::init("GetTransactionStatus", [
            'transfer_no' => $uuid,
        ]);
        if (!$response['status']) {
            return false;
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

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _Rich88Controller::init("Login", [
            'account' => $member->code,
        ]);

        $url = $response['data'] ?? null;

        return ($response['status'] && $url) ? ['url' => $url] : false;
    }

    public function updateStatus(Member $member)
    {
        return true;
    }
    public static function getBets($startDate, $endDate, $page = 1, $limit = 999)
    {

        $response = _Rich88Controller::init("GetBetLogs", [
            'from' => $startDate,
            'to' => $endDate,
            'page' => $page,
            'size' => $limit,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['bet_record_list'] ?? null;
    }
}
