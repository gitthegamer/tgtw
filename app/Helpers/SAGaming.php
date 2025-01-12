<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Transaction;
use App\Modules\_SAGamingController;
use Carbon\Carbon;

class SAGaming
{
    public $operator;
    public $product;
    public $secret_key;
    public $md5_key;
    public $encrypt_key;

    public function __construct(Operator $operator, Product $product)
    {
        $this->operator = $operator;
        $this->product = $product;
        $this->secret_key = explode(",", $product->key)[0];
        $this->md5_key = explode(",", $product->key)[1];
        $this->encrypt_key = explode(",", $product->key)[2];
    }

    public function getAccount(Member $member)
    {
        return $member;
    }

    public function createMember($account)
    {
        $response = _SAGamingController::init("RegUserInfo", [
            'method' => "RegUserInfo",
            'key' => $this->secret_key,
            'time' => Carbon::now()->format('YmdHis'),
            'username' => strtolower($account),
            'currencyType' => "MYR",
            'md5_key' => $this->md5_key,
            'encrypt_key' => $this->encrypt_key,
        ]);

        if (!$response['status']) {
            return false;
        }

        $member = Member::create([
            'operator_id' => $this->operator->id,
            'product_id' => $this->product->id,
            // 'operator_product' => $this->operator_product ? $this->operator_product->id : null,
            'account' => strtolower($account),
            'username' => strtolower($account),
            'password' => $this->randomPassword(),
            'custom' => []
        ]);

        return $member;
    }

    public function getBalance(Member $member)
    {
        $response = _SAGamingController::init("GetUserStatusDV", [
            'method' => "GetUserStatusDV",
            'key' => $this->secret_key,
            'time' => Carbon::now()->format('YmdHis'),
            'username' => strtolower($member->account),
            'md5_key' => $this->md5_key,
            'encrypt_key' => $this->encrypt_key,
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['Balance'];
    }

    public function deposit(Transaction $transaction)
    {
        $response = _SAGamingController::init("CreditBalanceDV", [
            'method' => "CreditBalanceDV",
            'key' =>$this->secret_key,
            'time' => Carbon::now()->format('YmdHis'),
            'username' => $transaction->member->username,
            'amount' => $transaction->amount,
            'md5_key' => $this->md5_key,
            'encrypt_key' => $this->encrypt_key,
        ]);

        if (!$response['status']) {
            return false;
        }

        return true;
    }

    public function withdraw(Transaction $transaction)
    {
        $response = _SAGamingController::init("DebitBalanceDV", [
            'method' => "DebitBalanceDV",
            'key' => $this->secret_key,
            'time' => Carbon::now()->format('YmdHis'),
            'username' => $transaction->member->username,
            'amount' => $transaction->amount,
            'md5_key' => $this->md5_key,
            'encrypt_key' => $this->encrypt_key,
        ]);

        if (!$response['status']) {
            return false;
        }

        return true;
    }

    public function startGame(Member $member, $gameid = null, $isMobile = false, $blimit = null, $lobby = 'A8028')
    {
        $response = _SAGamingController::init("SetBetLimit", [
            'method' => "SetBetLimit",
            'key' => $this->secret_key,
            'time' => Carbon::now()->format('YmdHis'),
            'currencyType' => "MYR",
            'username' => $member->username,
            'blimit' => $blimit,
            'md5_key' => $this->md5_key,
            'encrypt_key' => $this->encrypt_key,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        $response = _SAGamingController::init("LoginRequest", [
            'method' => "LoginRequest",
            'key' => $this->secret_key,
            'time' => Carbon::now()->format('YmdHis'),
            'username' => $member->username,
            'currencyType' => "MYR",
            'md5_key' => $this->md5_key,
            'encrypt_key' => $this->encrypt_key,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return _SAGamingController::generateLogin(strtolower($member->username), $response['data']['Token'], $lobby, $isMobile);
    }

    public function checkTransaction(Transaction $transaction)
    {
        if ($transaction->type == Transaction::DEPOSIT) {
            $time = $transaction->created_at->format('YmdHis');
            $username = $transaction->member->username;
            $transaction_id = "IN$time$username";
        }
        if ($transaction->type == Transaction::WITHDRAW) {
            $time = $transaction->created_at->format('YmdHis');
            $username = $transaction->member->username;
            $transaction_id = "OUT$time$username";
        }
        $response = _SAGamingController::init("CheckOrderDetailsDV", [
            'method' => "CheckOrderDetailsDV",
            'key' => $this->secret_key,
            'time' => Carbon::now()->format('YmdHis'),
            'orderId' => $transaction_id,
            'md5_key' => $this->md5_key,
            'encrypt_key' => $this->encrypt_key,
        ]);

        if (!$response['status'] || !$response['data']) {
            return false;
        }

        if (!isset($response['data'])) {
            return false;
        }

        if ($response['data']['isExist'] == true) {
            return [
                'status' => Transaction::SUCCESS,
                'remark' => $response['status_message'],
            ];
        }

        return [
            'status' => Transaction::FAIL,
            'remark' => $response['status_message'],
        ];
    }

    public static function getBets($date, $page = 1, $product)
    {
        $secret_key = explode(",", $product->key)[0];
        $md5_key = explode(",", $product->key)[1];
        $encrypt_key = explode(",", $product->key)[2];

        $response = _SAGamingController::init("GetAllBetDetailsDV", [
            'method' => "GetAllBetDetailsDV",
            'key' => $secret_key,
            'time' => Carbon::now()->format('YmdHis'),
            'date' => $date,
            'md5_key' => $md5_key,
            'encrypt_key' => $encrypt_key,
        ]);

        if (!$response['status']) {
            return false;
        }

        if (!$response['data']) {
            return false;
        }

        if ($response['data']['BetDetail'] > 0 && count($response['data']['BetDetail']) / 1000 > $page) {
            return array_merge($response['data']['BetDetail'], SELF::getBets($date, $page = 1, $product));
        }

        return $response['data']['BetDetail'];
    }

    public function randomPassword($len = 8)
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
