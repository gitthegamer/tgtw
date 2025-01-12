<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\GameLogKey;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_IBCController;
use Carbon\Carbon;

class IBC
{
    public $operator;
    public $product;
    public $vendor_id;
    public $operatorId;
    public $prefix;


    public function getAccount(Member $member)
    {
        return $member;
    }

    public static function create(Member $member)
    {
        $response = _IBCController::init("CreateMember", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'vendor_member_id' => config('api.IBC_PREFIX') . $member->code,
            'operatorId' => config('api.IBC_OPERATOR_ID'),
            'username' => config('api.IBC_PREFIX') . $member->code,
        ]);

        $resultSetBetLimit = self::bet_limit($member);
        if (!$resultSetBetLimit) {
            Helpers::sendNotification('IBC Set Bet limit Failed: ' . $member->username);
        }

        if (!$response['status'] && $response['status_code'] != 6) {
            return $member->member_accounts()->updateOrCreate([
                'member_id' => $member->id,
                'product_id' => $member->product_id,
            ], [
                'account' => $member->code,
                'username' => config('api.IBC_PREFIX') . $member->code,
                'password' => self::randomPassword(),
            ]);
        }

        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->code,
            'username' => config('api.IBC_PREFIX') . $member->code,
            'password' => self::randomPassword(),
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

    public static function balance($member)
    {
        $member_account = self::check($member);
        if (!$member_account) {
            return false;
        }

        $response = _IBCController::init("CheckUserBalance", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'vendor_member_ids' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'][0]['balance'] ?? 0;
    }

    public static function account(Member $member)
    {
        $account = SELF::check($member);
        return $account;
    }

    public static function withdrawal($member, $transfer)
    {
        if ($member->product) {
            $member_account = self::check($member);
            if (!$member_account) {
                return false;
            }

            $response = _IBCController::init("FundTransfer", [
                'vendor_id' => config('api.IBC_VENDOR_ID'),
                'vendor_member_id' => $member_account->username,
                'vendor_trans_id' => config('api.IBC_PREFIX') . $transfer->uuid,
                'amount' => $transfer->amount,
                'direction' => _IBCController::withdrawal,
            ]);

            if ($response['status'] == false) {
                return false;
            }
            switch ($response['data']['status']) {
                case 0:
                    return true;

                case 1:
                    return false;

                case 2:
                    return false;
            }
            return true;
        }
        return false;
    }

    public static function deposit($member, $transfer)
    {
        if ($member->product) {
            $member_account = self::check($member);
            if (!$member_account) {
                return false;
            }

            $response = _IBCController::init("FundTransfer", [
                'vendor_id' => config('api.IBC_VENDOR_ID'),
                'vendor_member_id' => $member_account->username,
                'vendor_trans_id' => config('api.IBC_PREFIX') . $transfer->uuid,
                'amount' => $transfer->amount,
                'direction' => _IBCController::deposit,
            ]);

            if ($response['status'] == false) {
                return false;
            }
            switch ($response['data']['status']) {
                case 0:
                    return true;

                case 1:
                    return false;

                case 2:
                    return false;
            }
            return true;
        }
        return false;
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

        $response = _IBCController::init("CheckFundTransfer", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'wallet_id' => 1,
            'vendor_trans_id' => config('api.IBC_PREFIX') . $transfer->uuid,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        if ($response['status_code'] == 2) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['status_message'],
            ];
        }

        if ($response['status_code'] != "0" && $response['status_code'] != "3" && $response['status_code'] != "10") {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => _IBCController::ERRORS["CheckFundTransfer"][$response['status_code']] ?? "Unknown Error",
            ];
        }

        if ($response['data']['status'] == 0) {
            return [
                'status' => Transfer::STATUS_SUCCESS,
                'remark' => _IBCController::ERRORS["CheckFundTransfer"][$response['status_code']],
            ];
        }

        if ($response['data']['status'] == 1) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['status_message'],
            ];
        }

        if ($response['data']['status'] == 2) {
            return [
                'status' => Transfer::STATUS_IN_PROGRESS,
                'remark' => $response['status_message'],
            ];
        }

        return false;
    }

    public static function account_withdrawal($member_account, $transfer)
    {

        $response = _IBCController::init("FundTransfer", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'vendor_member_id' => $member_account->username,
            'vendor_trans_id' => config('api.IBC_PREFIX') . $transfer->uuid,
            'amount' => $transfer->amount,
            'direction' => _IBCController::withdrawal,
        ]);

        if ($response['status'] == false) {
            return false;
        }
        switch ($response['data']['status']) {
            case 0:
                return true;

            case 1:
                return false;

            case 2:
                return false;
        }
        return true;
    }

    public static function account_balance($member_account)
    {
        $response = _IBCController::init("CheckUserBalance", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'vendor_member_ids' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'][0]['balance'] ?? 0;
    }

    public static function account_deposit($member_account, $transfer)
    {
        if (!$member_account) {
            return false;
        }

        $response = _IBCController::init("FundTransfer", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'vendor_member_id' => $member_account->username,
            'vendor_trans_id' => config('api.IBC_PREFIX') . $transfer->uuid,
            'amount' => $transfer->amount,
            'direction' => _IBCController::deposit,
        ]);
        if ($response['status'] == false) {
            return false;
        }
        switch ($response['data']['status']) {
            case 0:
                return true;

            case 1:
                return false;

            case 2:
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

        $response = _IBCController::init("GetSabaUrl", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'vendor_member_id' => $member_account->username,
            'platform' => !$isMobile ? 1 : 2,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        $resultSetBetLimit = self::bet_limit($member);
        if (!$resultSetBetLimit) {
            Helpers::sendNotification('IBC Set Bet limit Failed: ' . $member->username);
        }

        return [
            'url' => $response['data']
        ];
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getBets()
    {
        $version_key = cache()->get('ibc_version_key.' . config('api.IBC_VENDOR_ID'), [
            'key' => 0,
            'expired_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response = _IBCController::init("GetBetDetail", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'version_key' => $version_key['key'],
        ]);


        if (!$response['status']) {
            return false;
        }

        if ($version_key['key'] == $response['data']['last_version_key']) {
            if (now()->lte(Carbon::parse($version_key['expired_at']))) {
                return false;
            }
        }

        cache()->put('ibc_version_key.' . config('api.IBC_VENDOR_ID'), [
            'key' => $response['data']['last_version_key'],
            'expired_at' => now()->addMinutes(10)
        ]);

        GameLogKey::create([
            'class' => IBC::class,
            'key' => $response['data']['last_version_key'],
        ]);

        return isset($response['data']["BetDetails"]) ? $response['data']["BetDetails"] : [];
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

    private static function bet_limit($member)
    {
        $sports = [
            '1',
            '2',
            '3',
            '5',
            '8',
            '10',
            '11',
            '43',
            '99',
            '99MP',
            '1MP',
            '161',
            '9901',
            '9902'
        ];

        $bet_settings = [];

        foreach ($sports as $sport) {
            $setting = [
                'sport_type' => $sport,
                'min_bet' => 10,
                'max_bet' => 1000,
                'max_bet_per_match' => 1000,
                'max_payout_per_match' => 10000
            ];
            if ($sport == '161') {
                $setting['max_bet_per_ball'] = 1000;
            }
            $bet_settings[] = $setting;
        }

        $response = _IBCController::init("SetMemberBetSetting", [
            'vendor_id' => config('api.IBC_VENDOR_ID'),
            'vendor_member_id' => config('api.IBC_PREFIX') . $member->code,
            'bet_setting' => json_encode($bet_settings)
        ]);

        if (!$response['status']) {
            return false;
        }

        return true;
    }
}
