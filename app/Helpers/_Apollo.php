<?php

namespace App\Helpers;

use App\Models\Game;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_ApolloController;

class _Apollo
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $date = Carbon::now();
        $unixtime = $date->timestamp * 1000;
        $response = _ApolloController::init("Tr_CreateUser.aspx", [
            'action' => 2,
            'ts' => $unixtime,
            'parent' => config('api.APOLLO_AGENT'),
            'uid' => strtolower($member->code),
            'name' => strtolower($member->code),
            'credit_allocate' => 0,
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
        if ($member_account) {
            return $member_account;
        }

        return SELF::create($member);
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
        $date = Carbon::now();
        $unixtime = $date->timestamp * 1000;
        $response = _ApolloController::init("Tr_UserInfo.aspx", [
            'action' => 3,
            'ts' => $unixtime,
            'parent' => config('api.APOLLO_AGENT'),
            'uid' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['balance'];
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
        $date = Carbon::now();
        $unixtime = $date->timestamp * 1000;
        $response = _ApolloController::init("Tr_ChangeV.aspx", [
            'action' => 5,
            'ts' => $unixtime,
            'parent' => config('api.APOLLO_AGENT'),
            'uid' => $member_account->username,
            'serialNo' => $transfer->uuid,
            'amount' => $transfer->amount,
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
        $date = Carbon::now();
        $unixtime = $date->timestamp * 1000;
        $response = _ApolloController::init("Tr_ChangeV.aspx", [
            'action' => 5,
            'ts' => $unixtime,
            'parent' => config('api.APOLLO_AGENT'),
            'uid' => $member_account->username,
            'serialNo' => $transfer->uuid,
            'amount' => $transfer->amount * -1,
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

        $date = Carbon::now();
        $unixtime = $date->timestamp * 1000;
        $response = _ApolloController::init("Tr_singleV.aspx", [
            'action' => 9,
            'ts' => $unixtime,
            'parent' => config('api.APOLLO_AGENT'),
            'serialNo' => $uuid,
        ]);

        if (!$response['status']) {
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
        $date = Carbon::now();
        $unixtime = $date->timestamp * 1000;
        $response = _ApolloController::init("Tr_GameList.aspx", [
            'action' => 6,
            'ts' => $unixtime,
            'parent' => config('api.APOLLO_AGENT'),
            'lang' => _ApolloController::getLocale(),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data'];
    }    


    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);

        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        if (!$member_account) {
            return false;
        }

        $game = Game::where('code', $gameid)->first();
        if (!$game) {
            return false;
        }

        $date = Carbon::now();
        $unixtime = $date->timestamp * 1000;
        $response = _ApolloController::init("Tr_GetToken.aspx", [
            'action' => 1,
            'ts' => $unixtime,
            'uid' => $member_account->username,
            'lang' => _ApolloController::getLocale(),
            'gType' => $gameid,
            'windowMode' => 2,
            'lobbyURL' => config('api.MONEY_URL'),
            'backBtn' => false,
        ]);

        if (!$response['status']) {
            return product::ERROR_PROVIDER_MAINTENANCE;
        }
        return [
            'url' => $response['data']['path']
        ];
    }

    public static function getBets($startdate, $enddate)
    {
        $date = Carbon::now();
        $unixtime = $date->timestamp * 1000;
        $response = _ApolloController::init("Tr_QueryGameJsonResult.aspx", [
            'action' => 12,
            'ts' => $unixtime,
            'parent' => config('api.APOLLO_AGENT'),
            'uid' => 0, // Retrieve all player bet logs
            'starttime' => $startdate,
            'endtime' => $enddate,
            'lang' => _ApolloController::getLocale(),
            'gtype' => 0, // Retrieve all game bet logs 
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data'];
    }


    public static function bet_limit($game, $data)
    {
        return true;
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

    protected static $date;
    protected static $unixtime;

    // Initialize constants
    protected static function initializeTime()
    {
        self::$date = Carbon::now();
        self::$unixtime = self::$date->timestamp * 1000;
    }
}
