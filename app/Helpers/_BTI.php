<?php

namespace App\Helpers;

use App\Models\GameLogKey;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_BTIController;
use Illuminate\Support\Str;

class _BTI
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();

        $response = _BTIController::init("CreateUserNew", [
            'AgentUserName' => config('api.BTI_AGENT_USERNAME'),
            'AgentPassword' => config('api.BTI_AGENT_PASSWORD'),
            'MerchantCustomerCode' => $member->code,
            'LoginName' => $member->code,
            'CurrencyCode' => $member->currency,
            'CountryCode' => "MY",
            'City' => "KL",
            'FirstName' => str_replace(' ', '', $member->username),
            'LastName' => str_replace(' ', '', $member->username),
            'Group1ID' => 0,
            'CustomerDefaultLanguage' => _BTIController::getLocale(),
            'CustomerMoreInfo' => "",
            'DomainID' => "",
            'DateOfBirth' => "",
        ]);

        if ($response['status'] == false) {
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
        $response = _BTIController::init("GetBalance", [
            'AgentUserName' => config('api.BTI_AGENT_USERNAME'),
            'AgentPassword' => config('api.BTI_AGENT_PASSWORD'),
            'MerchantCustomerCode' => $member_account->account,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return ($response['data']['Balance']);
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
        $response = _BTIController::init("TransferToWHL", [
            'AgentUserName' => config('api.BTI_AGENT_USERNAME'),
            'AgentPassword' => config('api.BTI_AGENT_PASSWORD'),
            'MerchantCustomerCode' => $member_account->account,
            'Amount' => $transfer->amount,
            'RefTransactionCode' => Str::uuid(),
            'BonusCode' => '',
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
        $response = _BTIController::init("TransferFromWHL", [
            'AgentUserName' => config('api.BTI_AGENT_USERNAME'),
            'AgentPassword' => config('api.BTI_AGENT_PASSWORD'),
            'MerchantCustomerCode' => $member_account->account,
            'Amount' => $transfer->amount,
            'RefTransactionCode' => Str::uuid(),
        ]);

        if ($response['status'] == false) {
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

        $response = _BTIController::init("CheckTransaction", [
            'AgentUserName' => config('api.BTI_AGENT_USERNAME'),
            'AgentPassword' => config('api.BTI_AGENT_PASSWORD'),
            'MerchantCustomerCode' => $member_account->account,
        ]);

        if (!$response['status']) {
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

        $response = _BTIController::init("GetCustomerAuthToken", [
            'AgentUserName' => config('api.BTI_AGENT_USERNAME'),
            'AgentPassword' => config('api.BTI_AGENT_PASSWORD'),
            'MerchantCustomerCode' => $member_account->account,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        if ($isMobile) {
            return [
                'url' => config('api.BTI_LINK') . '/' . _BTIController::getLocale() . '/sports/' . $response['data']['CustomerAuthToken'] . '/?operatorToken=' . $response['data']['AuthToken'],
            ];
        }

        return [
            'url' => config('api.BTI_LINK') . '/' . _BTIController::getLocale() . '/asian-view/' . $response['data']['CustomerAuthToken'] . '/?operatorToken=' . $response['data']['AuthToken'],
        ];
    }

    public static function getBets($startTime, $endTime)
    {
        $response = _BTIController::init("bettinghistory", [
            'AgentUserName' => config('api.BTI_AGENT_USERNAME'),
            'AgentPassword' => config('api.BTI_AGENT_PASSWORD'),
            'From' => $startTime,
            'To' => $endTime,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['result'] == null || $response['data']['result'] == '') {
            return false;
        }

        return json_decode($response['data']['result'], true);
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

        while (strlen($password) < $len) {
            $randomSet = $sets[array_rand($sets)];
            $password .= $randomSet[array_rand(str_split($randomSet))];
        }

        return str_shuffle($password);
    }
}
