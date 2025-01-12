<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_King855Controller;

class _King855
{
    public static function create(Member $member)
    {
        $password = SELF::randomPassword();
        
        $response = _King855Controller::init("signup", [
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'apikey' => config('api.KING855_API_KEY'),
            'random' => SELF::randomGUID(),
            'data' => null,
            'member' => [
                "username" => $member->code,
                "password" => md5($password),
                "currencyName" => "MYR",
                "winLimit" => 0
            ]
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

        $response = _King855Controller::init("getBalance", [
            'apikey' => config('api.KING855_API_KEY'),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::randomGUID(),
            'member' => [
                'username' => $member_account->username
            ]
        ]);

        if (!$response['status']) {
            return false;
        }

         return $response['data']['member']['balance'];
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _King855Controller::init("transfer", [
            'apikey' => config('api.KING855_API_KEY'),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::randomGUID(),
            'data' => $transfer->uuid,
            'member' => [
                'username' => $member_account->username,
                'amount' => $transfer->amount
            ]
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

        $response = _King855Controller::init("transfer", [
            'apikey' => config('api.KING855_API_KEY'),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::randomGUID(),
            'data' => $transfer->uuid,
            'member' => [
                'username' => $member_account->username,
                'amount' => $transfer->amount*-1
            ]
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }
    public static function account_balance($member_account)
    {
        $response = _King855Controller::init("getBalance", [
            'apikey' => config('api.KING855_API_KEY'),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::randomGUID(),
            'member' => [
                'username' => $member_account->username
            ]
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['member']['balance'];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _King855Controller::init("transfer", [
            'apikey' => config('api.KING855_API_KEY'),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::randomGUID(),
            'data' => $transfer->uuid,
            'member' => [
                'username' => $member_account->username,
                'amount' => $transfer->amount*-1
            ]
        ]);

        if (!$response['status']) {
            return false;
        }
        return $response['status'];
    }

    public static function account_deposit($member_account, $transfer)
    {
        $response = _King855Controller::init("transfer", [
            'apikey' => config('api.KING855_API_KEY'),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::randomGUID(),
            'data' => $transfer->uuid,
            'member' => [
                'username' => $member_account->username,
                'amount' => $transfer->amount
            ]
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

        $response = _King855Controller::init("checkTransfer", [
            'apikey' => config('api.KING855_API_KEY'),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::randomGUID(),
            'data' => $transfer->uuid
        ]);

        if (!$response['status']) {
            if ($response['data']['codeId'] == 324) {
                return [
                    'status' => Transfer::STATUS_FAIL,
                    'remark' => $response['data']['token'],
                ];
            }
            return false;
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => $response['data'],
        ];
    }


    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);

        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        // _King855Controller::init("update", [
        //     'apikey' => config('api.KING855_API_KEY'),
        //     "token" => $member_account->username,
        //     'random' => SELF::getTimestamp(),
        //     'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
        //     "member" => [
        //         "username" => $member_account->username,
        //         "password" => $member_account->password,
        //         "winLimit" => 0,
        //         "status" => 1
        //     ]
        // ]);
        
        SELF::bet_limit($member_account, $member);

        $response = _King855Controller::init("login", [
            'apikey' => config('api.KING855_API_KEY'),
            "token" => $member_account->username,
            'random' => SELF::getTimestamp(),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            "lang" => _King855Controller::getLocale(),
            'domains' => "1",
            "member" => [
                "username" => $member_account->username,
                "password" => $member_account->password 
            ]
        ]);

        if (!$response['status']) {
            return false;
        }
        
        if($isMobile){
            return [
                'url' => $response['data']['list'][1].$response['data']['token'].'&language='._King855Controller::getLocale()
            ];
        }
        return [
            'url' => $response['data']['list'][0].$response['data']['token'].'&language='._King855Controller::getLocale()
        ];
    }

    public static function bet_limit($member_account, $member)
    {
        $bet_limit = $member->getBetLimit();
        if (!$bet_limit) {
            return;
        }

        _King855Controller::init("updateLimit", [
            'apikey' => config('api.KING855_API_KEY'),
            "token" => $member_account->username,
            'random' => SELF::getTimestamp(),
            'agentacc' => config('api.KING855_AGENT_ACCOUNT'),
            'data' => $bet_limit['code'] ?? 'F',
            "member" => [
                "username" => $member_account->username,
                "password" => $member_account->password,
                "winLimit" => 0,
                "status" => 1
            ]
        ]);
    }

    public static function getBets()
    {
    
        $response = _King855Controller::init("getReport", [
            'apikey' => config('api.KING855_API_KEY'),
            "agentacc" => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::getTimestamp(),
        ]);


        if (!$response['status']) {
            return [];
        }
    
        return $response['data']['list'] ?? [];
    }

    public static function updateBets($input){
        $response = _King855Controller::init("mark", [
            'apikey' => config('api.KING855_API_KEY'),
            "agentacc" => config('api.KING855_AGENT_ACCOUNT'),
            'random' => SELF::getTimestamp(),
            'list' => $input
        ]);

        if (!$response['status']) {
            return false;
        }

        return true;
    }

    public static function getTimestamp(){
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
