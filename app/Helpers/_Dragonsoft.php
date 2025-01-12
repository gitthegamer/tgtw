<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_DragonsoftController;
use Illuminate\Support\Facades\Log;

class _Dragonsoft
{


    public static function create(Member $member)
    {
        $password = self::generate_password();
        $response = _DragonsoftController::init("member/create", [
            'agent' => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member->code,
            "password" => $password,
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
            return self::create($member);
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
        $member_account = self::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _DragonsoftController::init("trans/check_balance", [
            "agent" => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['balance'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = self::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _DragonsoftController::init("trans/transfer", [
            'serial' => $transfer->uuid,
            'agent' => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member_account->username,
            'amount' => $transfer->amount,
            'oper_type' => 1,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = self::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _DragonsoftController::init("trans/transfer", [
            'serial' => $transfer->uuid,
            'agent' => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member_account->username,
            'amount' => $transfer->amount,
            'oper_type' => 0,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _DragonsoftController::init("trans/check_balance", [
            'agent' => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['balance'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _DragonsoftController::init("trans/transfer", [
            'serial' => $transfer->uuid,
            'agent' => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member_account->username,
            'amount' => $transfer->amount,
            'oper_type' => 0,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _DragonsoftController::init("trans/transfer", [
            'serial' => $transfer->uuid,
            'agent' => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member_account->username,
            'amount' => $transfer->amount,
            'oper_type' => 1,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
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

   
        $response = _DragonsoftController::init("trans/verify", [
            'agent' => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member_account->username,
            "serial" => $transfer->uuid,
        ]);

        if (!$response['status']) {
            if($response['data']['result']['code'] == 1006){
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

    public static function getGameList()
    {
        $response = _DragonsoftController::init("config/get_game_info_state_list", [
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data']['game_info_state_list'];
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

        $response = _DragonsoftController::init("member/login_game", [
            'game_id' => $gameid,
            'agent' => config('api.DS_AGENT_ACCOUNT'),
            'account' => $member_account->username,
            'lang' => _DragonsoftController::getLocale(),
            'backurl' => route('home'),
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['url'],
        ];

    }
    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBets($startDate, $endDate, $page)
    {


        $response = _DragonsoftController::init("record/get_bet_records", [
            'finish_time' => [
                "start_time" => $startDate,
                "end_time" => $endDate,
            ],
            'index' => $page,
            'limit' => 5000

        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['total'] > 0 && count($response['data']['rows']) < $response['data']['total'] && count($response['data']['rows']) != 0) {
            sleep(10);
            return array_merge($response['data']['rows'], self::getBets($startDate, $endDate, $page + 1));
        }

        return $response['data']['rows'];
    }

    public static function generate_password()
    {
        return "Abcd" . rand(1000, 9999);
    }


}