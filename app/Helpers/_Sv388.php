<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\Transfer;
use App\Modules\_Sv388Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers;
use App\Models\BetLog;
use App\Models\Product;

class _Sv388
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $memberCode = str_replace(' ', '', $member->code);
        $memberCode = substr($memberCode, 0, 20);
        $response = _Sv388Controller::init("/wallet/createMember", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            'userId' => $member->code,
            'currency' => config('api.SV388_CURRENCY'),
            'betLimit' => '{"SV388":{"LIVE":{"maxbet":1000,"minbet":1,"mindraw":1,"matchlimit":1000,"maxdraw":100}}}',
            'language' => _Sv388Controller::getLocale(),
            'userName' => $memberCode,
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
        $member_account = SELF::check($member);
        if (!$member_account) {
            return false;
        }

        return SELF::account_balance($member_account);
    }

    public static function account_balance($member_account)
    {
        $response = _Sv388Controller::init("/wallet/getBalance", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            "alluser" => 0,
            'userIds' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['results'][0]['balance'];
    }

    public static function checkTransaction($uuid, $callCount = 0)
    {
        $maxCalls = 5;

        $transfer = Transfer::where('uuid', $uuid)->first();
        if (!$transfer) {
            return false;
        }

        $member_account = SELF::check($transfer->member);
        if (!$member_account) {
            return false;
        }

        sleep(40);

        $response = _Sv388Controller::init("/wallet/checkTransferOperation", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            'txCode' => $transfer->uuid,
        ]);

        if ($response['data']['status'] == '0000') {
            if ($response['data']['txStatus'] === "1") {
                return [
                    'status' => Transfer::STATUS_SUCCESS,
                    'remark' => json_encode($response),
                ];
            } elseif ($response['data']['txStatus'] === "0") {
                return [
                    'status' => Transfer::STATUS_FAIL,
                    'remark' => json_encode($response),
                ];
            } elseif ($response['data']['txStatus'] === "2" && $callCount < $maxCalls) {
                return SELF::checkTransaction($uuid, $callCount + 1);
            }
        } elseif ($response['data']['status'] == '1017') {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => json_encode($response),
            ];
        }

        if (!$response['status']) {
            return false;
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => json_encode($response),
        ];
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
        $response = _Sv388Controller::init("/wallet/deposit", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            'userId' => $member_account->username,
            'txCode' => $transfer->uuid,
            'transferAmount' => $transfer->amount
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
        $response = _Sv388Controller::init("/wallet/withdraw", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            'userId' => $member_account->username,
            'txCode' => $transfer->uuid,
            'withdrawType' => 1,
            'transferAmount' => $transfer->amount
        ]);

        if (!$response['status']) {
            return false;
        }

        return true;
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $response = _Sv388Controller::init("/wallet/login", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            'userId' => $member_account->username,
            'isMobileLogin' => $isMobile,
            'externalURL' => config('api.MONEY_URL'),
            'platform' => 'SV388',
            'gameType' => 'LIVE',
            'betLimit' => '{"SV388":{"LIVE":{"maxbet":1000,"minbet":1,"mindraw":1,"matchlimit":1000,"maxdraw":100}}}',
            'language' => _Sv388Controller::getLocale(),
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['url'],
        ];
    }

    public static function getBets($date)
    {
        $response = _Sv388Controller::init("/fetch/gzip/getTransactionByUpdateDate", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            "timeFrom" => Carbon::parse($date)->toIso8601String(),
            'platform' => "SV388",
            'currency' => config('api.SV388_CURRENCY'),
            'gameType' => "LIVE",
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['transactions'];
    }

    public static function getHourlyBetLog($startTime, $endTime)
    {
        $response = _Sv388Controller::init("/fetch/getSummaryByTxTimeHour", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            'startTime' => Carbon::parse($startTime)->format('Y-m-d\THP'),
            'endTime' => Carbon::parse($endTime)->format('Y-m-d\THP'),
            'platform' => "SV388",
            'currency' => config('api.SV388_CURRENCY'),
        ]);

        if (!$response['status']) {
            return [];
        }

        $result = $response['data']['transactions'];
        if (count($result) == 0) {
            return [];
        }

        $betAmount = 0;
        $winAmount = 0;

        foreach ($result as $item) {
            $betAmount += $item['betAmount'];
            $winAmount += $item['winAmount'];
        }

        $local_betAmount = 0;
        $local_winAmount = 0;

        $local_result = BetLog::where('round_at', '>=', Carbon::parse($startTime)->format('Y-m-d\TH:i:s'))
            ->where('round_at', '<', Carbon::parse($endTime)->format('Y-m-d\TH:i:s'))
            ->where('product', "SV388")
            ->get();

        if ($local_result != null) {
            $local_betAmount = $local_result->sum('stake');
            $local_winAmount = $local_result->sum('payout');
        }

        $betAmountValue = number_format($betAmount, 2, '.', '');
        $winAmountValue = number_format($winAmount, 2, '.', '');
        $local_betAmountValue = number_format($local_betAmount, 2, '.', '');
        $local_winAmountValue = number_format($local_winAmount, 2, '.', '');

        $betAmount = (float)$betAmountValue;
        $winAmount = (float)$winAmountValue;
        $local_betAmount = (float)$local_betAmountValue;
        $local_winAmount = (float)$local_winAmountValue;


        if ($betAmount !== $local_betAmount && $winAmount !== $local_winAmount) {

            $response = _Sv388Controller::init("/fetch/gzip/getTransactionByTxTime", [
                'cert' => config('api.SV388_CERT'),
                'agentId' => config('api.SV388_USER_ID'),
                'startTime' => Carbon::parse($startTime)->format('Y-m-d\TH:i:sP'),
                'endTime' => Carbon::parse($endTime)->format('Y-m-d\TH:i:sP'),
                'platform' => "SV388",
                'currency' => config('api.SV388_CURRENCY'),
            ]);


            if (!$response['status']) {
                return [];
            }
            return $response['data']['transactions'];
        }

        // return [];
    }

    public static function getTips($date)
    {
        $endTime = $date->copy()->toIso8601String();
        $startTime = $date->copy()->subHours(3)->toIso8601String();

        $response = _Sv388Controller::init("/fetch/gzip/getTipTxnByTxTime", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            'startTime' => $startTime,
            'endTime' => $endTime,
            'platform' => 'SV388',
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['transactions'];
    }

    public static function getTipsSummary($date)
    {
        $endTime = Carbon::parse($date)->format('Y-m-d\TH') . Carbon::parse($date)->format('P');
        $startTime = Carbon::parse($date)->subHours(3)->format('Y-m-d\TH') . Carbon::parse($date)->subHours(3)->format('P');

        $response = _Sv388Controller::init("/fetch/getTipSummary", [
            'cert' => config('api.SV388_CERT'),
            'agentId' => config('api.SV388_USER_ID'),
            'startTimeHr' => $startTime,
            'endTimeHr' => $endTime,
            'platform' => 'SV388',
            'currency' => config('api.SV388_CURRENCY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['transactions'];
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
}
