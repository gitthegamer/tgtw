<?php

namespace App\Helpers;

use App\Models\Game;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_FunhouseController;

class _Funhouse
{

    public function getAccount(Member $member)
    {
        return $member;
    }

    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $response = _FunhouseController::init("player/add", [
            'secureToken'           => config('api.FH_SECURE_TOKEN_LIVE'),
            'external_player_id'    => $member->code,
            'currency'              => 'MYR',
            'secret'                => config('api.FH_SECRET_KEY_LIVE')
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
        $account = SELF::check($member);
        return $account;
    }

    public static function balance($member)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return false;
        }

        $response = _FunhouseController::init("player/balance", [
            'secureToken'               => config('api.FH_SECURE_TOKEN_LIVE'),
            'external_player_id'        => $member_account->username,
            'secret'                    => config('api.FH_SECRET_KEY_LIVE')
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data']['balance'];
    }

    public static function account_balance($member_account)
    {
        $response = _FunhouseController::init("player/balance", [
            'secureToken'               => config('api.FH_SECURE_TOKEN_LIVE'),
            'external_player_id'        => $member_account->username,
            'secret'                    => config('api.FH_SECRET_KEY_LIVE')
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data']['balance'];
    }


    public static function deposit($member, $transfer)
    {
        if ($member->product) {
            $member_account = SELF::check($member);
            if (!$member_account) {
                return false;
            }

            $response = _FunhouseController::init("balance/transfer", [
                'secureToken'               => config('api.FH_SECURE_TOKEN_LIVE'),
                'external_player_id'        => $member_account->username,
                'external_transaction_id'   => $transfer->uuid,
                'amount'                    => $transfer->amount,
                'secret'                    => config('api.FH_SECRET_KEY_LIVE')
            ]);

            if ($response['status'] == false) {
                return false;
            }
            return true;
        }
    }

    public static function account_deposit($member_account, $transfer)
    {
        if (!$member_account) {
            return false;
        }

        $response = _FunhouseController::init("balance/transfer", [
            'secureToken'               => config('api.FH_SECURE_TOKEN_LIVE'),
            'external_player_id'        => $member_account->username,
            'external_transaction_id'   => $transfer->uuid,
            'amount'                    => $transfer->amount,
            'secret'                    => config('api.FH_SECRET_KEY_LIVE')
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

        $response = _FunhouseController::init("balance/transferStatus", [
            'secureToken'               => config('api.FH_SECURE_TOKEN_LIVE'),
            'external_transaction_id'   => $transfer->uuid,
            'secret'                    => config('api.FH_SECRET_KEY_LIVE')
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['error'] != "") {
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

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _FunhouseController::init("balance/transfer", [
            'secureToken'               => config('api.FH_SECURE_TOKEN_LIVE'),
            'external_player_id'        => $member_account->username,
            'external_transaction_id'   => $transfer->uuid,
            'amount'                    => $transfer->amount * -1,
            'secret'                    => config('api.FH_SECRET_KEY_LIVE')
        ]);

        if ($response['status'] == false) {
            return false;
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

            $response = _FunhouseController::init("balance/transfer", [
                'secureToken'               => config('api.FH_SECURE_TOKEN_LIVE'),
                'external_player_id'        => $member_account->username,
                'external_transaction_id'   => $transfer->uuid,
                'amount'                    => $transfer->amount * -1,
                'secret'                    => config('api.FH_SECRET_KEY_LIVE')
            ]);

            if ($response['status'] == false) {
                return false;
            }
        }
        return true;
    }

    public static function getGameList()
    {
        $response = _FunhouseController::init("games", [
            'secureToken'           => config('api.FH_SECURE_TOKEN_LIVE'),
            'secret'                => config('api.FH_SECRET_KEY_LIVE'),
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data'];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        if ($member->product) {
            $member_account = SELF::check($member);
            if (!$member_account) {
                return Product::ERROR_ACCOUNT;
            }

            $game = Game::where('code', $gameid)->first();
            if (!$game) {
                return false;
            }

            $response = _FunhouseController::init("startGame", [
                'secureToken'               => config('api.FH_SECURE_TOKEN_LIVE'),
                'external_player_id'        => $member_account->username,
                'game_id'                   => $gameid,
                'language'                  => SELF::getLocale(),
                'game_mode'                 => "c",
                'secret'                    => config('api.FH_SECRET_KEY_LIVE'),
                'currency'                  => "MYR"
            ]);

            if (!$response['status']) {
                return Product::ERROR_PROVIDER_MAINTENANCE;
            }

            return [
                'url' => $response['data']['url'],
            ];
        }
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {

        $response = _FunhouseController::init("game/history", [
            'secureToken' => config('api.FH_SECURE_TOKEN_LIVE'),
            'secret' => config('api.FH_SECRET_KEY_LIVE'),
            'timepoint_start' => $startDate,
            'timepoint_end' => $endDate,
            'page_number' => $page,
        ]);

        if (!$response['status']) {
            return false;
        }

        if ($response['data']['result']['next_page']) {
            return array_merge($response['data']['result']['transactions'][0], SELF::getBets($startDate, $endDate, $page + 1));
        }

        return $response['data']['result']['transactions'][0];
    }


    public static function getLocale()
    {
        if (request()->lang == "en") {
            return "en";
        }
        if (request()->lang == "cn") {
            return "cn";
        }
        return "en";
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
