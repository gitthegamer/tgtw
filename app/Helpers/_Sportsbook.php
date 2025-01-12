<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_SportsbookController;
use Illuminate\Support\Facades\Log;

class _Sportsbook
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        //using this 'web-root/restricted/agent/register-agent.aspx' 
        //to create API agent first, 
        //if first time connect SBO 
        $response = _SportsbookController::init("web-root/restricted/player/register-player.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member->code,
            'Agent' => config('api.SBO_AGENT'),
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

        $response = _SportsbookController::init("web-root/restricted/player/get-player-balance.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['balance'] - $response['data']['outstanding'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _SportsbookController::init("web-root/restricted/player/deposit.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member_account->username,
            'Amount' => $transfer->amount,
            'txnId' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _SportsbookController::init("web-root/restricted/player/withdraw.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member_account->username,
            'Amount' => $transfer->amount,
            'txnId' => $transfer->uuid,
            'IsFullAmount' => true
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _SportsbookController::init("web-root/restricted/player/get-player-balance.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['balance'] - $response['data']['outstanding']; // update!
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _SportsbookController::init("web-root/restricted/player/withdraw.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member_account->username,
            'Amount' => $transfer->amount,
            'txnId' => $transfer->uuid,
            'IsFullAmount' => true
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _SportsbookController::init("web-root/restricted/player/deposit.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member_account->username,
            'Amount' => $transfer->amount,
            'txnId' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
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

        $response = _SportsbookController::init("web-root/restricted/player/check-transaction-status.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'txnId' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            if ($response['error']['id'] == 4601) {
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


    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $limit = _SportsbookController::init("web-root/restricted/player/update-player-bet-settings.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member_account->username,
            'min' => 1,
            'max' => 1000,
            'MaxPerMatch' => 1000,
            'CasinoTableLimit' => 4
        ]);

        $response = _SportsbookController::init("web-root/restricted/player/login.aspx", [
            'CompanyKey' => config('api.SBO_COMPANY_KEY'),
            'ServerId' => SELF::randomGUID(),
            'Username' => $member_account->username,
            'Portfolio' => 'SportsBook'
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        $language = _SportsbookController::getLocale();

        if($isMobile){
            return [
                'url' => 'https://'.$response['data']['url'] . '&lang=' . $language . '&device=m'
            ];
        }

        return [
            'url' => 'https://'.$response['data']['url'] . '&lang=' . $language . '&device=d'
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBets($startDate, $endDate)
    {
        $response = _SportsbookController::init("web-root/restricted/report/v2/get-bet-list-by-modify-date.aspx", [
            "Portfolio" => 'SportsBook',
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            "CompanyKey" => config('api.SBO_COMPANY_KEY'),
            "isGetDownline " => false,
            "ServerId" => SELF::randomGUID(),

        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['result'];
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

    public static function randomGUID($len = 8)
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
