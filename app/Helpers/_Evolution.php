<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_EvolutionController;
use App\Modules\_WCasinoController;
use Illuminate\Support\Facades\Log;
use Nette\Schema\Expect;

class _Evolution
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $name = str_replace(' ', '', $member->code);
        $bet_limit = SELF::bet_limit($member);

        $response = _EvolutionController::init("ua/v1", [
            'uuid' => SELF::randomGUID(),
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            'player_id' => $member->code,
            'player_update' => true,
            'player_firstName' => $name,
            'player_lastName' => $name,
            'player_country' => 'MY',
            'player_language' => _EvolutionController::getLocale(),
            'player_currency' => 'MYR',
            'session_ip' => "143.244.134.175",
            'session_id' => SELF::randomGUID(),
            'group_id' => $bet_limit,
            'action' => 'assign'
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
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _EvolutionController::init("RWA", [
            'cCode' => 'RWA',
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            "euID" => $member_account->username,
            'output' => '0'
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['userbalance']['tbalance'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _EvolutionController::init("ECR", [
            'cCode' => 'ECR',
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            'euID' => $member_account->username,
            'amount' => $transfer->amount,
            'eTransID' => $transfer->uuid,
            'createuser' => 'N',
            'output' => '0', // 0 = JSON, 1 = XML
        ]);
        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _EvolutionController::init("EDB", [
            'cCode' => 'EDB',
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            'euID' => $member_account->username,
            'amount' => $transfer->amount,
            'eTransID' => $transfer->uuid,
            'output' => '0'
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _EvolutionController::init("RWA", [
            'cCode' => 'RWA',
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            "euID" => $member_account->username,
            'output' => '0'
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['userbalance']['tbalance'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {

        $response = _EvolutionController::init("EDB", [
            'cCode' => 'EDB',
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            'euID' => $member_account->username,
            'amount' => $transfer->amount,
            'eTransID' => $transfer->uuid,
            'output' => '0'
        ]);


        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _EvolutionController::init("ECR", [
            'cCode' => 'ECR',
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            'euID' => $member_account->username,
            'amount' => $transfer->amount,
            'eTransID' => $transfer->uuid,
            'createuser' => 'N',
            'output' => '0', // 0 = JSON, 1 = XML
        ]);
        if (!$response['status']) {
            return false;
        }
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

        $response = _EvolutionController::init("TRI", [
            'cCode' => 'TRI',
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            'euID' => $member_account->username,
            'eTransID' => $transfer->uuid,
            'output' => '0'

        ]);

        if (!$response['status']) {
            return false;
        }


        if ($response['data']['transaction']['result'] != 'Y') {
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

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }
        $request = request();
        $bet_limit = SELF::bet_limit($member);

        $response = _EvolutionController::init("ua/v1", [
            'uuid' => SELF::randomGUID(),
            'ecID' => config('api.EVOLUTION_KEY_LIVE'),
            'player_id' => $member_account->username,
            'player_update' => false,
            'player_firstName' => str_replace(' ', '', $member_account->username),
            'player_lastName' => str_replace(' ', '', $member_account->username),
            'player_country' => 'MY',
            'player_language' => _EvolutionController::getLocale(),
            'player_currency' => 'MYR',
            'session_ip' => (request()->header('x-vapor-source-ip') !== null) ? request()->header('x-vapor-source-ip') : $request->server('HTTP_CF_CONNECTING_IP', $request->server('REMOTE_ADDR')),
            'session_id' => SELF::randomGUID(),
            'group_id' => $bet_limit,
            'action' => 'assign'
        ]);


        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        return [
            'url' => $response['data']['entry']
        ];
    }

    public static function bet_limit($member)
    {
        $bet_limit = $member->getBetLimit();
        if (!$bet_limit) {
            return;
        }
        return $bet_limit['code'] ?? config('api.EVOLUTION_GROUP_ID') ?? null;
    }

    public static function getBets($startDate, $endDate)
    {
        $now = now();
        $response = _EvolutionController::init("gamehistory", [

            "startDate" => $startDate, //UTC+0
            "endDate" => $endDate, //UTC+0
            "ecID" => config('api.EVOLUTION_KEY_LIVE'),
        ]);

        if (!$response['status']) {
            return false;
        }

        $betList = [];
        foreach ($response['data']['data'] as $betArray) {
            $betList = array_merge($betList, $betArray['games']);
        }


        return $betList;
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

    public static function randomGUID($len = 8)
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
