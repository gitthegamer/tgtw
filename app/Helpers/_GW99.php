<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_GW99Controller;

class _GW99
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        $response = _GW99Controller::init("Player/Create", [
            'PlayerAccount' => $member->code,
            'Password' => $password = "Abcd" . rand(1000, 9999),
            'Agent' => config('api.GW99_AGENT'),
        ]);

        if ($response['status'] == false) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => strtolower(config('api.GW99_AGENT') . $member->code),
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
        $response = _GW99Controller::init("Player/Info", [
            'PlayerAccount' => $member_account->username
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
        $response = _GW99Controller::init("Transaction/Create", [
            'PlayerAccount' => $member_account->username,
            'Amount' => $transfer->amount,
            'ExternalTransactionId' => $transfer->uuid,
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
        $FreezeResponse = _GW99Controller::init("Player/Freeze", [
            'PlayerAccount' => $member_account->username,
        ]);

        if ($FreezeResponse['status'] == false) {
            return false;
        }

        $KickResponse = _GW99Controller::init("Player/Kick", [
            'PlayerAccount' => $member_account->username,
        ]);

        if ($KickResponse['status'] == false) {
            return false;
        }

        $unFreezeResponse = _GW99Controller::init("Player/Unfreeze", [
            'PlayerAccount' => $member_account->username,
        ]);

        if ($unFreezeResponse['status'] == false) {
            return false;
        }

        $balanceResponse = _GW99Controller::init("Player/Info", [
            'PlayerAccount' => $member_account->username
        ]);

        if ($balanceResponse['status'] == false) {
            return false;
        }

        sleep(16);

        $withdrawalBalance = isset($balanceResponse['data']['balance']) ? $balanceResponse['data']['balance'] : $transfer->amount;
        if (!is_numeric($withdrawalBalance)) {
            $withdrawalBalance = (float) $transfer->amount;
        }

        $transfer->update([
            'amount' => $withdrawalBalance,
        ]);

        $WithdrawalResponse = _GW99Controller::init("Transaction/Create", [
            'PlayerAccount' => $member_account->username,
            'Amount' => $withdrawalBalance * -1,
            'ExternalTransactionId' => $transfer->uuid,
        ]);

        if ($WithdrawalResponse['status'] == false) {
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

        $response = _GW99Controller::init("Transaction/Check", [
            'PlayerAccount' => $member_account->username,
            'ExternalTransactionId' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return [
                'status' => Transfer::STATUS_IGNORE,
                'remark' => json_encode($response),
            ];
            return false;
        }

        if ($response['data']['result']['code'] !== "0" && $response['data']['result']['code'] !== 0) {
            return [
                'status' => Transfer::STATUS_FAIL,
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
        $response = _GW99Controller::init("GameList", []);

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

        $response = _GW99Controller::init("Player/Token", [
            'PlayerAccount' => $member_account->username,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        if ($response['data'] && $response['data']['Token']) {
            return [
                'url' => config('api.GW99_LOBBY_LINK') . "?altoken=" . $response['data']['Token']
            ];
        }

        return false;
    }

    public static function getBets($start_date, $end_date, $page = 1)
    {

        $response = _GW99Controller::init("Game/Log/Agent", [
            'PageNumber' => $page,
            'StartDate' => $start_date,
            "EndDate" => $end_date,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['page']['pageNumber'] < $response['data']['page']['totalPage']) {
            return array_merge($response['data']['record'], SELF::getBets($start_date, $end_date, $page + 1));
        }


        return $response['data']['record'];
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
