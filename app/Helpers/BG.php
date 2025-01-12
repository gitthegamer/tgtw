<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_BGController;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BG
{
    public function getAccount(Member $member)
    {
        return $member;
    }

    public static function create(Member $member)
    {
        $response = _BGController::init("open.user.create", [
            'random' => Str::uuid(),
            'sn' => config('api.BG_SN'),
            'loginId' => $member->code,
            'agentLoginId' => config('api.BG_AGENT_ID'),
            'password' => config('api.BG_AGENT_PW'),
        ]);


        if (!$response['status']) {
            return false;
        }

        usleep(100000);
        $member_account = MemberAccount::where('member_id', $member->id)
            ->where('product_id', $member->product_id)
            ->first();

        if (!$member_account) {
            return $member->member_accounts()->updateOrCreate([
                'member_id' => $member->id,
                'product_id' => $member->product_id,
            ], [
                'account' => $member->code,
                'username' => $member->code,
                'password' => SELF::randomPassword(),
            ]);
        } else {
            return $member_account;
        }



        // if (isset($response['status']) && $response['status'] === true || (isset($response['error']['code']) && $response['error']['code'] === '2206')) {
        //     return $member->member_accounts()->updateOrCreate([
        //         'member_id' => $member->id,
        //         'product_id' => $member->product_id,
        //     ], [
        //         'account' => $member->code,
        //         'username' => $member->code,
        //         'password' => SELF::randomPassword(),
        //     ]);
        // } else {
        //     return false;
        // }

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
        $account = SELF::check($member);
        return $account;
    }



    public static function balance($member)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return false;
        }

        $response = _BGController::init("open.balance.get", [
            'random' => Str::uuid(),
            'password' => config('api.BG_AGENT_PW'),
            'sn' => config('api.BG_SN'),
            'loginId' => $member_account->username,
        ]);

        if (!$response || !$response['status']) {
            return false;
        }

        return $response['data'];
    }

    public static function deposit($member, $transfer)
    {
        if ($member->product) {
            $member_account = SELF::check($member);
            if (!$member_account) {
                return false;
            }

            $response = _BGController::init("open.balance.transfer", [
                'random' => Str::uuid(),
                'password' => config('api.BG_AGENT_PW'),
                'sn' => config('api.BG_SN'),
                'loginId' => $member_account->username,
                'amount' => (config('api.BG_ENV') !== 'production') ? min($transfer->amount, 200) : $transfer->amount,
                'bizId' => $transfer->id,

            ]);

            if (!$response['status']) {
                return false;
            }
        }
        return true;
    }

    public static function withdrawal($member, $transfer)
    {
        if ($member->product) {
            $member_account = SELF::check($member);
            if (!$member_account) {
                return false;
            }

            $response = _BGController::init("open.balance.transfer", [
                'random' => Str::uuid(),
                'password' => config('api.BG_AGENT_PW'),
                'sn' => config('api.BG_SN'),
                'loginId' => $member_account->username,
                'amount' => (config('api.BG_ENV') !== 'production') ? min($transfer->amount * -1, 200) : $transfer->amount * -1,
                'bizId' => $transfer->id,

            ]);

            if (!$response['status']) {
                return false;
            }
        }

        return true;
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $blimit = config('api.BG_BET_LIMIT');

        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $response = _BGController::init("open.video.game.url", [
            'random' => Str::uuid(),
            'sn' => config('api.BG_SN'),
            'loginId' => $member_account->username,
            'password' => config('api.BG_AGENT_PW')
        ]);

        if ($response['status'] == false) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        SELF::bet_limit($member_account, $member);

        return [
            'url' => $response['data']
        ];
    }

    public static function account_balance($member_account)
    {
        if (!$member_account) {
            return false;
        }

        $response = _BGController::init("open.balance.get", [
            'random' => Str::uuid(),
            'password' => config('api.BG_AGENT_PW'),
            'sn' => config('api.BG_SN'),
            'loginId' => $member_account->username,
        ]);



        if (!$response || !$response['status']) {
            return false;
        }

        return $response['data'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        if (!$member_account) {
            return false;
        }

        $response = _BGController::init("open.balance.transfer", [
            'random' => Str::uuid(),
            'password' => config('api.BG_AGENT_PW'),
            'sn' => config('api.BG_SN'),
            'loginId' => $member_account->username,
            'amount' => (config('api.BG_ENV') !== 'production') ? min($transfer->amount * -1, 200) : $transfer->amount * -1,
            'bizId' => $transfer->id,

        ]);

        if (!$response['status']) {
            return false;
        }
        return true;
    }

    public static function account_deposit($member_account, $transfer)
    {
        if (!$member_account) {
            return false;
        }

        $response = _BGController::init("open.balance.transfer", [
            'random' => Str::uuid(),
            'password' => config('api.BG_AGENT_PW'),
            'sn' => config('api.BG_SN'),
            'loginId' => $member_account->username,
            'amount' => (config('api.BG_ENV') !== 'production') ? min($transfer->amount, 200) : $transfer->amount,
            'bizId' => $transfer->id,

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

        $response = _BGController::init("open.balance.transfer.query", [
            'random' => Str::uuid(),
            'secret_key' => config('api.BG_SECRET_KEY'),
            'sn' => config('api.BG_SN'),
            'loginId' => $member_account->username,
            'bizId' => $transfer->id,

        ]);

        if (!$response['status'] || !$response['data']) {
            return false;
        }

        if (!isset($response['data'])) {
            return false;
        }

        if (!isset($response['data']['items'])) {
            return false;
        }

        foreach ($response['data']['items'] as $item) {
            if ($item['bizId'] == $transfer->id) {
                return [
                    'status' => Transfer::STATUS_SUCCESS,
                    'remark' => json_encode($response),
                ];
            }
        }

        return [
            'status' => Transfer::STATUS_FAIL,
            'remark' => json_encode($response),
        ];
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        $response = _BGController::init("open.order.agent.query", [
            "random" => Str::uuid(),
            "sn" => config('api.BG_SN'),
            "startTime" => $startDate,
            "endTime" => $endDate,
            'secret_key' => config('api.BG_SECRET_KEY'),
            'pageIndex' => $page,
            'agentLoginId' => config('api.BG_AGENT_ID'),
            'password' => config('api.BG_AGENT_PW'),
        ]);

        if (!$response || !$response['status']) {
            return false;
        }

        if ($response['data']['total'] > 0 && ($response['data']['total'] / 1000) > $page) {
            return array_merge($response['data']['items'], SELF::getBets($startDate, $endDate, $page + 1));
        }

        return $response['data']['items'];
    }

    public static function getRoundBets($reqTime, $orderId)
    {
        $response = _BGController::init("open.sn.video.order.detail", [
            "random" => Str::uuid(),
            'reqTime' => $reqTime,
            "sn" => config('api.BG_SN'),
            "orderId" => $orderId,
            'secret_key' => config('api.BG_SECRET_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];
    }

    public static function bet_limit($member_account, $member)
    {
        $bet_limit = $member->getBetLimit();
        if (!$bet_limit) {
            return;
        }

        _BGController::init("open.game.limitations.set", [
            'random' => Str::uuid(),
            'secret_key' => config('api.BG_SECRET_KEY'),
            'sn' => config('api.BG_SN'),
            'time' => Carbon::now()->format('Y-m-d H:i:s'),
            'loginId' => $member_account->username,
            'value' => $bet_limit['code'] ?? null,
        ]);
    }

    public static function getBetLimitList()
    {
        $response = _BGController::init("open.game.limitations.list", [
            "random" => Str::uuid(),
            "sn" => config('api.BG_SN'),
            'time' => Carbon::now()->format('Y-m-d H:i:s'),
            'secret_key' => config('api.BG_SECRET_KEY'),
        ]);
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
