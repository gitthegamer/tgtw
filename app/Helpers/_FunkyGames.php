<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_FunkyGamesController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class _FunkyGames
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        $response = _FunkyGamesController::init("Funky/Game/LaunchGame", [
            'currency' => 'MYR',
            'gameCode' => 0,
            'language' => _FunkyGamesController::getLocale(),
            'playerId' => $member->code,
            'playerIp' => (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'] ?? "143.244.134.175"),
            'redirectUrl' => config('api.MONEY_URL'),
            'sessionId' => Str::uuid(), 
            'userName' => str_replace(' ', '', $member->username),
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
        if ($account = SELF::check($member)) {
            return $account;
        }
        return SELF::create($member);
    }

    public static function balance($member)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _FunkyGamesController::init("Funky/Wallet/GetBalanceByCurrency", [
            'currency' => 'MYR',
            'playerId' => $member_account->username
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['balance'];
    }

    public static function deposit($member, $transfer)
    {
        
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _FunkyGamesController::init("Funky/Wallet/Deposit", [
            'amount' => $transfer->amount,
            'currency' => 'MYR',
            'isTestAccount' => false,
            'playerId' => $member_account->username,
            'txId' => $transfer->uuid,
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

        $response = _FunkyGamesController::init("Funky/Wallet/Withdraw", [
            'amount' => $transfer->amount,
            'currency' => 'MYR',
            'isTestAccount' => false,
            'playerId' => $member_account->username,
            'txId' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {

        $response = _FunkyGamesController::init("Funky/Wallet/GetBalanceByCurrency", [
            'currency' => 'MYR',
            'playerId' => $member_account->username
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['data']['balance'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _FunkyGamesController::init("Funky/Wallet/Withdraw", [
            'amount' => $transfer->amount,
            'currency' => 'MYR',
            'isTestAccount' => false,
            'playerId' => $member_account->username,
            'txId' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _FunkyGamesController::init("Funky/Wallet/Deposit", [
            'amount' => $transfer->amount,
            'currency' => 'MYR',
            'isTestAccount' => false,
            'playerId' => $member_account->username,
            'txId' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function getGameList()
    {
        $response = _FunkyGamesController::init("Funky/Game/GetLobbyGameList", [
            'language' => _FunkyGamesController::getLocale(),
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data']['gameList'];
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

        $response = _FunkyGamesController::init("Funky/Wallet/CheckTransaction", [
            'playerId' => $member_account->username,
            'txId' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            return false;
        }

        if($response['data']['data']['status'] == 'Processing'){
            return [
                'status' => Transfer::STATUS_IN_PROGRESS,
                'remark' => json_encode($response),
            ];
        }
       
        if($response['data']['data']['status'] == 'Invalidated'){
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => json_encode($response),
            ];
        }

        // if($response['data']['data']['status'] == 'Invalidated'){
        //     return [
        //         'status' => Transfer::STATUS_IN_PROGRESS,
        //         'remark' => json_encode($response),
        //     ];
        // }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => json_encode($response),
        ];
      
    }


    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }
        
        $member_account = SELF::check($member);

        if (!$member_account) {
            return false;
        }

        $response = _FunkyGamesController::init("Funky/Game/LaunchGame", [
            'currency' => 'MYR',
            'gameCode' => 0,
            'language' => _FunkyGamesController::getLocale(),
            'playerId' => $member_account->username,
            'playerIp' => (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'] ?? "143.244.134.175"),
            'redirectUrl' => config('api.MONEY_URL'),
            'sessionId' => Str::uuid(), 
            'userName' => str_replace(' ', '', $member->full_name),
        ]);

        if (!$response['status']) {
            return false;
        }

        return [
            'url' => $response['data']['data']['gameUrl'].'?token='.$response['data']['data']['token'],
        ];
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        $allBets = [];

        $response = _FunkyGamesController::init("Funky/Report/GetBetList", [
            'page' => $page,
            'startTime' => $startDate, 
            'endTime' => $endDate,
        ]);

        if (!$response['status']) {
            return [];
        }
        
        if($response['data']['totalPage'] > 0) {
            $allBets = array_merge($allBets, $response['data']['data']);
        }

        if ($response['data']['totalPage'] > 1 && $response['data']['totalPage'] > $page) {
             $allBets = array_merge($allBets, self::getBets($startDate, $endDate, $page + 1));
        }

        return  $allBets;
    }


    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getTimestamp(){
        return round(microtime(true) * 1000);
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