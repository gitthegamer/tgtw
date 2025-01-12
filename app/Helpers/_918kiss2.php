<?php

namespace App\Helpers;

use App\Jobs\ProcessKiss2InsertBetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_918kiss2Controller;
use Carbon\Carbon;

class _918kiss2
{
    public static function create(Member $member)
    {
        $response = _918kiss2Controller::init("player", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "operation" => "addnewplayer",
            "playername" => $member->code,
            "playertelno" => strval(SELF::randomPhone()),
            "playerdescription" => "",
            "playerpassword" => $password = SELF::randomPassword(),
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
                'username' => $response['data']['playerid'],
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
        $response = _918kiss2Controller::init("player", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "operation" => "getplayerinfo",
            "playerid" => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['balance'];
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
        $response = _918kiss2Controller::init("funds", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "operation" => "predeposit",
        ]);

        if (!$response['status']) {
            return false;
        }
        $transfer->update(['unique_id' => $tid = $response['data']['tid']]);
        $response = _918kiss2Controller::init("funds", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "operation" => "deposit",
            "playerid" => $member_account->username,
            "amount" => (string) $transfer->amount,
            "tid" => $tid,
        ]);

        return $response['status'];
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
        $response = _918kiss2Controller::init("funds", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "operation" => "prewithdraw",
        ]);

        if (!$response['status']) {
            return false;
        }

        $transfer->update(['unique_id' => $tid = $response['data']['tid']]);

        $response = _918kiss2Controller::init("funds", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "operation" => "withdraw",
            "playerid" => $member_account->username,
            "amount" => (string) $transfer->amount,
            "tid" => $tid,
        ]);

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

        $response = _918kiss2Controller::init("funds", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "operation" => "checkstatus",
            "tid" => $transfer->unique_id,
        ]);

        if (!$response['status']) {
            if ($response['data']['returncode'] == -1005) {
                return [
                    'status' => Transfer::STATUS_IGNORE,
                    'remark' => $response['data']['message'],
                ];
            }
            return false;
        }

        if ($response['data']['returncode'] != 0) {

            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['data']['message'],
            ];
        }

        if ($response['data']['apireturncode'] === -411) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['data']['message'],
            ];
        }

        if (
            $response['data']['apireturncode'] === -303 || $response['data']['apireturncode'] === -403 ||
            $response['data']['apireturncode'] === -1005 || $response['data']['apireturncode'] === -1007 ||
            $response['data']['apireturncode'] === -1007 || $response['data']['apireturncode'] === -304 ||
            $response['data']['apireturncode'] === -406 || $response['data']['apireturncode'] === -407 ||
            $response['data']['apireturncode'] === -408 || $response['data']['apireturncode'] === -409 ||
            $response['data']['apireturncode'] === -502 || $response['data']['apireturncode'] === -600 ||
            $response['data']['apireturncode'] === -404 || $response['data']['apireturncode'] === -700 ||
            $response['data']['apireturncode'] === -1005
        ) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['data']['message'],
            ];
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => $response['data']['message'],
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

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function product_logs($date, $userName)
    {
        $currentDateTime = Carbon::parse($date);
        $logs = [];
        $currentDate = $currentDateTime->format('Y-m-d');
        $currentTime = $currentDateTime->format('H:i:s');

        if ($currentDateTime->format('H') < 1) {
            $startDateYesterday = $currentDateTime->copy()->subDay()->format('Y-m-d');
            $startTimeYesterday = $currentDateTime->copy()->subDay()->subMinutes(30)->format('H:i:s');
            $endTimeYesterday = '23:59:59';

            $responseYesterday = _918kiss2Controller::init("reports", [
                "apiuserid" => config('api.KISS2_API_USER'),
                "apipassword" => config('api.KISS2_API_PASS'),
                "playerid" => $userName,
                "operation" => "gamelog",
                "date" => $startDateYesterday,
                "starttime" => $startTimeYesterday,
                "endtime" => $endTimeYesterday,
            ]);

            if (!$responseYesterday['status']) {
                return false;
            } else {
                $logs = array_merge($logs, $responseYesterday['data']['game_logs']);
            }
        }

        $startTimeToday = $currentDateTime->copy()->subMinutes(15)->format('H:i:s');
        $responseToday = _918kiss2Controller::init("reports", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "playerid" => $userName,
            "operation" => "gamelog",
            "date" => $currentDate,
            "starttime" => $startTimeToday,
            "endtime" => $currentTime,
        ]);

        if (!$responseToday['status']) {
            return false;
        } else {
            $logs = array_merge($logs, $responseToday['data']['game_logs']);
        }

        ProcessKiss2InsertBetLog::dispatch($logs, $userName);
    }

    public static function get_player_list($date)
    {
        $response = _918kiss2Controller::init("reports", [
            "apiuserid" => config('api.KISS2_API_USER'),
            "apipassword" => config('api.KISS2_API_PASS'),
            "operation" => "totalreport",
            "startdate" => Carbon::parse($date)->copy()->subMinutes(15)->format('Y-m-d'),
            "enddate" => Carbon::parse($date)->copy()->format('Y-m-d'),
        ]);

        if (!$response['status']) {
            return [];
        }

        return $response['data']['player_bets'];
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

    // public static function generate_username()
    // {
    //     $phone = "601" . random_int(100000000, 9999999999);
    //     while (!preg_match('/^(\+?6?01)[0-9]{7,8}/i', $phone)) {
    //         $phone = "601" . random_int(100000000, 9999999999);
    //     }

    //     if (MemberAccount::where('product_id', 14)->where('username', $phone)->first()) {
    //         return SELF::generate_username();
    //     }



    //     return $phone;
    // }

    public static function randomPhone()
    {
        $phone = random_int(100000, 9999999);
        return $phone;
    }
}
