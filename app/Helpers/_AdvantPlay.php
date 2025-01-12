<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_AdvantPlayController;
use DateTime;

class _AdvantPlay
{
    public static function create(Member $member)
    {
        $password = self::randomPassword();

        $response = _AdvantPlayController::init("CreatePlayer", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'PlayerId' => $member->code,
            'PlayerName' => str_replace(' ', '', $member->code),
            'Currency' => 'MYR',
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

        $response = _AdvantPlayController::init("GetBalance", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'OPToken' => self::randomPassword(),
            'PlayerId' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Balance'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = self::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _AdvantPlayController::init("TransferIn", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'PlayerId' => $member_account->username,
            "OPTransferID" => $transfer->uuid,
            "Currency" => 'MYR',
            "Amount" => $transfer->amount,
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

        $response = _AdvantPlayController::init("TransferOut", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'PlayerId' => $member_account->username,
            "OPTransferID" => $transfer->uuid,
            "Currency" => 'MYR',
            "Amount" => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _AdvantPlayController::init("GetBalance", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'OPToken' => self::randomPassword(),
            'PlayerId' => $member_account->username,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Balance'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _AdvantPlayController::init("TransferOut", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'PlayerId' => $member_account->username,
            "OPTransferID" => $transfer->uuid,
            "Currency" => 'MYR',
            "Amount" => $transfer->amount,
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _AdvantPlayController::init("TransferIn", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'PlayerId' => $member_account->username,
            "OPTransferID" => $transfer->uuid,
            "Currency" => 'MYR',
            "Amount" => $transfer->amount,
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

        $response = _AdvantPlayController::init("CheckTransfer", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            "OPTransferID" => $transfer->uuid,
        ]);

        if (!$response['status']) {
            if($response['data']['ErrorCode'] == 5000){
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
        $response = _AdvantPlayController::init("GetGameList", [
            "Timestamp" => self::getTimestamp(),
            "Seq" => config('api.ADVANT_PLAY_SEQ'),
            "Size" => "492x660",
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data'];

    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $response = _AdvantPlayController::init("GetPlayerToken", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'PlayerId' => $member_account->username,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }
      
        $response = _AdvantPlayController::init("GetLaunchURL", [
            'Timestamp' => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'PlayerId' => $member_account->username,
            'Token' => $response['data']['Token'],
            'LangCode' => _AdvantPlayController::getLocale(),
            'GameCode' => '10001',
            'LaunchLobby' => true,
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }
      
        return [
            'url' => $response['data']['LaunchURL']
        ];
    }

    public static function bet_limit($game, $data)
    {
        return true;
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {

        $response = _AdvantPlayController::init("GetBatchHistory", [
            "Timestamp" => self::getTimestamp(),
            'Seq' => config('api.ADVANT_PLAY_SEQ'),
            'BrandCode' => 'default',
            'SiteCode' => 'default',
            'DateFrom' => $startDate,
            'DateTo' => $endDate,
            'Page' => $page,
            'PageSize' => 1000,
        ]);



        if (!$response['status']) {
            return [];
        }


        if(!isset($response['data']['Results'])){
            return [];
        }

        if (($response['data']['Info']['Records'] / 1000) > $page) {
            return array_merge($response['data']['Results'], self::getBets($startDate, $endDate, $page + 1));
        }

        return $response['data']['Results'];
    }

    public static function getTimestamp()
    {
        $date = new DateTime(); 
        $now = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
        $nowFormatted = $now->format('Y-m-d\TH:i:s.u');
        return $nowFormatted;
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