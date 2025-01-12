<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_XE88Controller;

class _XE88
{
    public $operator;
    public $product;
    public $agent_id;
    public $prefix;
    public $signature_key;

    public static function create(Member $member)
    {
        $response = _XE88Controller::init("player/create", [
            'agentid' => config('api.XE88_AGENT_ID'),
            'account' => $username = config('api.XE88_PREFIX') . strtolower($member->code),
            'password' => $password = SELF::randomPassword(),
            'signature_key' => config('api.XE88_SIGNATURE_KEY'),
        ]);

        if (!$response['status']) {
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
        $response = _XE88Controller::init("player/info", [
            'agentid' => config('api.XE88_AGENT_ID'),
            'account' => $member_account->username,
            'signature_key' => config('api.XE88_SIGNATURE_KEY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['result']['balance'];
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
        $response = _XE88Controller::init("player/deposit", [
            'agentid' => config('api.XE88_AGENT_ID'),
            'account' => $member_account->username,
            'amount' => $transfer->amount,
            'trackingid' => $transfer->uuid,
            'signature_key' => config('api.XE88_SIGNATURE_KEY')
        ]);

        if ($response['status'] == false) {
            return false;
        } else {
            return true;
        }

        return false;
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
        $response = _XE88Controller::init("player/withdraw", [
            'agentid' => config('api.XE88_AGENT_ID'),
            'account' => $member_account->username,
            'amount' => $transfer->amount,
            'trackingid' => $transfer->uuid,

            'signature_key' => config('api.XE88_SIGNATURE_KEY')
        ]);

        if ($response['status'] == false) {
            return false;
        } else {
            return true;
        }

        return false;
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


        $response = _XE88Controller::init("player/checktransaction", [
            'agentid' => config('api.XE88_AGENT_ID'),
            'trackingid' => $transfer->uuid,

            'signature_key' => config('api.XE88_SIGNATURE_KEY')
        ]);

        if (!$response['status'] && !$response['data']) {
            return false;
        }

        if ($response['data']['code'] != 0) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => $response['status_message'],
            ];
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => $response['status_message'],
        ];
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

        if ($isMobile) {
            $isMobile = "1";
        } else {
            $isMobile = "0";
        }

        return [
            'url' => config('api.XE88_GAME_LINK') . '?language=En&gameid=' . $game->code .
                '&userid=' . $member_account->username .
                '&userpwd=' . md5($member_account->password)
        ];
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getBets($date, $startTime, $endTime, $page = 1)
    {
        $response = _XE88Controller::init("customreport/playergamelog", [
            'agentid' => config('api.XE88_AGENT_ID'),
            'account' => null,
            'date' => $date,
            'starttime' => $startTime,
            'endtime' => $endTime,
            'page' => $page,
            'perpage' => 500,
            'signature_key' => config('api.XE88_SIGNATURE_KEY'),
        ]);


        if (!$response['status'] || $response['data']['code'] != 0) {
            return false;
        }

        if ($response['data']['pagination']['totalpages'] > $page) {
            $nextPageBets = SELF::getBets($date, $startTime, $endTime, $page + 1);
            return array_merge($response['data']['result'], is_array($nextPageBets) ? $nextPageBets : []);
        }
        

        return $response['data']['result'];
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
