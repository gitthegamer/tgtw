<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_QB838Controller;
use Exception;

class _QB838
{
    public static function create(Member $member)
    {
        $response = _QB838Controller::init("SportApi/Register", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            'MemberAccount' => $member->code,
            'Currency' => "MYR",
        ]);

        if (!$response['status']) {
            return false;
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => strtolower(config('api.QB838_PREFIX') . $member->code),
            'password' => SELF::randomPassword(),
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
        $response = _QB838Controller::init("SportApi/GetBalance", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            'MemberAccount' => $member_account->account,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Balance'] ?? 0;
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
        $key = strtolower(config('api.QB838_APIPASS') . $member_account->account . number_format($transfer->amount, 4, '.', ''));

        $response = _QB838Controller::init("SportApi/Transfer", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            'MemberAccount' => $member_account->account,
            'SerialNumber' => config('api.QB838_PREFIX') . $transfer->uuid,
            'Amount' => $transfer->amount,
            'TransferType' => 0,
            'Key' => substr(md5($key), -6),
        ]);
        if ($response['status'] == false) {
            return false;
        }
        if ($response['status_code'] !== "000000") {
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
        $key = strtolower(config('api.QB838_APIPASS') . $member_account->account . number_format($transfer->amount, 4, '.', ''));

        $response = _QB838Controller::init("SportApi/Transfer", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            'MemberAccount' => $member_account->account,
            'SerialNumber' => config('api.QB838_PREFIX') . $transfer->uuid,
            'Amount' => $transfer->amount,
            'TransferType' => 1,
            'Key' => substr(md5($key), -6),
        ]);
        if ($response['status'] == false) {
            return false;
        }
        if ($response['status_code'] !== "000000") {
            return false;
        }

        $response = _QB838Controller::init("SportApi/Logout", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            'MemberAccount' => $member_account->account,
        ]);

        if (!$response['status']) {
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

        $response = _QB838Controller::init("SportApi/CheckTransfer", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            'SerialNumber' => config('api.QB838_PREFIX') . $transfer->uuid,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        if ($response['status_code'] !== "000000") {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['status_message'],
            ];
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


        $response = _QB838Controller::init("SportApi/SetGameParamLimit", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            'MemberAccount' => $member_account->account,
            'Items' => _QB838Controller::getGameParamLimiteList(),
        ]);


        $response = _QB838Controller::init("SportApi/Login", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            'MemberAccount' => $member_account->account,
            'LoginIP' => (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'] ?? "143.244.134.175"),
            'Language' => _QB838Controller::getLocale(),
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        $url = 'https:' . str_replace('\/', '/', $response['data']);
        return [
            'url' => $url,
        ];
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getBets($SortNo = null, $Rows = 500, $isSkipStore = false)
    {
        $allBets = [];

        if (!$SortNo && !$isSkipStore) {
            $SortNo = cache()->get('qb838_sort_no', 0);
        }

        $response = _QB838Controller::init("SportApi/GetBetSheetBySort", [
            'CompanyKey' => config('api.QB838_KEY'),
            'APIPassword' => config('api.QB838_APIPASS'),
            // 'AgentID' => config('api.QB838_AGENT'),
            'SortNo' => $SortNo,
            'Rows' => $Rows,
        ]);

        if (!$response['status']) {
            return [];
        }

        if ($response['status_code'] !== "000000") {
            return [];
        }

        $bets = $response['data'];
        if (!empty($bets)) {
            $allBets = array_merge($allBets, $bets);
            // Get the maximum SortNo from the current batch of bets
            $maxSortNo = max(array_column($bets, 'SortNo'));
            if ($maxSortNo != 0) {
                cache()->put(
                    'qb838_sort_no',
                    $maxSortNo
                );
            }
            if (count($bets) < $Rows) {
                return $allBets;
            }
            // Recursive call to get the next batch of bets
            $allBets = array_merge($allBets, self::getBets($maxSortNo, $Rows));
        }

        return  $allBets;
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
