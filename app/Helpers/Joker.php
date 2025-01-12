<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_JokerController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Joker
{

    public function getAccount(Member $member)
    {
        return $member;
    }

    public static function create(Member $member)
    {

        $response = _JokerController::init("CU", [
            "Username" => $username = $member->code,
        ]);

        if ($response['status_code'] != 200 && $response['status_code'] != 201) {
            return false;
        }

        $response = _JokerController::init("SP", [
            "Username" => $username,
            "Password" => $password = _JokerController::generate_password(),
        ]);

        if ($response['status_code'] != 200) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => $username,
            'password' => $password,
        ]);
    }

    public static function check(Member $member)
    {
        $member_account = $member->member_accounts()->where('member_id', $member->id)->where('product_id', $member->product_id)->first();
        if (!$member_account) {
            return self::create($member);
        }

        return $member_account;
    }

    public static function account(Member $member)
    {
        $account = SELF::check($member);
        return $account;
    }


    public static function balance($member)
    {
        $member_account = self::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _JokerController::init("GC", [
            "Username" => $member_account->username,
        ]);

        if ($response['status'] == false) {
            return false;
        }


        return $response['data']['Credit'];
    }

    public static function account_balance($member_account)
    {
        $response = _JokerController::init("GC", [
            "Username" => $member_account->username,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data']['Credit'];
    }


    public static function withdrawal($member, $transfer)
    {
        if ($member->product) {

            $member_account = self::account($member);
            if (!$member_account) {
                return false;
            }

            $response = _JokerController::init("TC", [
                "Username" => $member_account->username,
                "RequestID" => $transfer->uuid,
                "Amount" => $transfer->amount * -1,
            ]);

            if ($response['status_code'] >= 400 && $response['status_code'] < 500) {
                return false;
            } else if ($response['status_code'] >= 500) {
                return false;
            } else {
                return true;
            }
        }
    }

    public static function deposit($member, $transfer)
    {
        if ($member->product) {
            $member_account = self::check($member);
            if (!$member_account) {
                return false;
            }

            $response = _JokerController::init("TC", [
                "Method" => "TC",
                "Username" => $member_account->username,
                "Timestamp" => time(),
                "RequestID" => $transfer->uuid,
                "Amount" => $transfer->amount,
            ]);

            if ($response['status_code'] >= 400 && $response['status_code'] < 500) {
                return false;
            } else if ($response['status_code'] >= 500) {
                return false;
            } else {
                return true;
            }
        }
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _JokerController::init("TC", [
            "Method" => "TC",
            "Username" => $member_account->username,
            "Timestamp" => time(),
            "RequestID" => $transfer->uuid,
            "Amount" => $transfer->amount * -1,
        ]);

        if ($response['status_code'] >= 400 && $response['status_code'] < 500) {
            return false;
        } else if ($response['status_code'] >= 500) {
            return false;
        } else {
            return true;
        }
    }

    public static function account_deposit($member_account, $transfer)
    {

        $response = _JokerController::init("TC", [
            "Method" => "TC",
            "Username" => $member_account->username,
            "Timestamp" => time(),
            "RequestID" => $transfer->uuid,
            "Amount" => $transfer->amount,
        ]);

        if ($response['status_code'] >= 400 && $response['status_code'] < 500) {
            return false;
        } else if ($response['status_code'] >= 500) {
            return false;
        } else {
            return true;
        }
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

        $response = _JokerController::init("TCH", [
            "RequestID" => $transfer->uuid,
        ]);

        if ($response['status_code'] >= 400 && $response['status_code'] < 500) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['message'],
            ];
        } else if ($response['status_code'] >= 500) {
            return [
                'status' => Transfer::STATUS_IN_PROGRESS,
                'remark' => "Connection Error",
            ];
        } else {
            return [
                'status' => Transfer::STATUS_SUCCESS,
                'remark' => $response['message'],
            ];
        }
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $game = Game::where('code', $gameid)->first();
        if (!$game) {
            return false;
        }

        $response = _JokerController::init("PLAY", [
            "Username" => $member_account->username,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        if ($isMobile) {
            $isMobile = "1";
        } else {
            $isMobile = "0";
        }
        return [
            'url' => config('api.JOKER_FORWARD_URL') . "?token=" . $response['data']['Token'] . "&game=" . $gameid . "&redirectUrl=" . route('home') . "&mobile=$isMobile&lang=" . _JokerController::getLocale() . ""
        ];
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getGameList()
    {
        $response = _JokerController::init("ListGames", []);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
    }

    public static function getBets(Carbon $time, $isMinutes = true, $version_key = '')
    {
        $array = array();

        $response = self::fetchBets($time, $isMinutes, $version_key);

        if (!$response) {
            return $array;
        }

        if (isset($response['data']['Game'])) {
            $array = array_merge($array, $response['data']['Game']);
        }

        if (isset($response['data']['Jackpot'])) {
            $array = array_merge($array, $response['data']['Jackpot']);
        }

        if (isset($response['data']['Competition'])) {
            $array = array_merge($array, $response['data']['Competition']);
        }

        if (isset($response['nextId']) && $response['nextId'] != "") {
            return array_merge(
                $array,
                self::getBets($time, $isMinutes, $response['nextId'])
            );
        }


        return $array;
    }

    public static function fetchBets(Carbon $time, $isMinutes, $version_key = '')
    {
        $response = _JokerController::init($isMinutes ? "TSM" : "TS", [
            "Date" => $time,
            "NextId" => $version_key
        ]);


        if (!$response['status']) {
            return false;
        }

        return $response['data'];
    }
}
