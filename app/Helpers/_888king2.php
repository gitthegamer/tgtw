<?php

namespace App\Helpers;

use App\Models\GameLogKey;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_888kingController2;

class _888king2
{
    public $operator;
    public $product;
    public $agent_id;
    public $prefix;
    public $secret;


    public function getAccount(Member $member)
    {
        return $member;
    }

    public static function create(Member $member)
    {
        $password = self::randomPassword();

        $response = _888kingController2::init("api/user/create", [
            'host_id' => config('api.888KING_HOST_ID_LIVE'),
            'member_id' => $username =  $member->code . rand(1000, 9999),
            'currency' => 'MYR',
        ]);

        if ($response['status'] == false) {
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
                'account' => $username,
                'username' => $username,
                'password' => $password,
            ]);
        } else {
            return $member_account;
        }
    }

    public static function check(Member $member)
    {
        $member_account = $member->member_accounts()->where('member_id', $member->id)->where('product_id', $member->product_id)->first();
        if (!$member_account) {
            return self::create($member);
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
        $member_account = self::check($member);
        if (!$member_account) {
            return false;
        }

        $response = _888kingController2::init("api/user/balance", [
            'host_id' => config('api.888KING_HOST_ID_LIVE'),
            'member_id' => $member_account->username
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return ($response['data']['balance'] / 100);
    }

    public static function account_balance($member_account)
    {
        $response = _888kingController2::init("api/user/balance", [
            'host_id' => config('api.888KING_HOST_ID_LIVE'),
            'member_id' => $member_account->username
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return ($response['data']['balance'] / 100);
    }


    public static function deposit($member, $transfer)
    {
        if ($member->product) {
            $member_account = self::check($member);
            if (!$member_account) {
                return false;
            }

            $response = _888kingController2::init("api/user/deposit-v2", [
                'host_id' => config('api.888KING_HOST_ID_LIVE'),
                'member_id' => $member_account->username,
                'amount' => $transfer->amount * 100,
                'transid' => $transfer->uuid,
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

        $response = _888kingController2::init("api/user/deposit-v2", [
            'host_id' => config('api.888KING_HOST_ID_LIVE'),
            'member_id' => $member_account->username,
            'amount' => $transfer->amount * 100,
            'transid' => $transfer->uuid,
        ]);

        if ($response['status'] == false) {
            return false;
        }
        return true;
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

        $response = _888kingController2::init("api/user/wallet-trans-status", [
            'host_id' => config('api.888KING_HOST_ID_LIVE'),
            'trans_id' => $transfer->uuid,
        ]);

        if (!$response['status']) {
            if ($response['data']['error'] && $response['data']['error']['status_code'] == 1) {
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

    public static function account_withdrawal($member_account, $transfer)
    {
        $response = _888kingController2::init("api/user/withdraw-v2", [
            'host_id' => config('api.888KING_HOST_ID_LIVE'),
            'member_id' => $member_account->username,
            'amount' => $transfer->amount * 100,
            'transid' => $transfer->uuid,
        ]);

        if ($response['status'] == false) {
            return false;
        }
        return true;
    }


    public static function withdrawal($member, $transfer)
    {
        if ($member->product) {
            $member_account = self::check($member);
            if (!$member_account) {
                return false;
            }

            $response = _888kingController2::init("api/user/withdraw-v2", [
                'host_id' => config('api.888KING_HOST_ID_LIVE'),
                'member_id' => $member_account->username,
                'amount' => $transfer->amount * 100,
                'transid' => $transfer->uuid,
            ]);

            if ($response['status'] == false) {
                return false;
            }
        }
        return true;
    }

    public static function getGameList()
    {
        $response = _888kingController2::init("api/user/gamelist", [
            'host_id' => config('api.888KING_HOST_ID_LIVE'),
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

            $response = _888kingController2::init("api/user/generate-access-token", [
                'host_id' => config('api.888KING_HOST_ID_LIVE'),
                'member_id' => $member_account->username
            ]);

            if (!$response['status']) {
                return Product::ERROR_PROVIDER_MAINTENANCE;
            }

            // return [
            //     'url' => $game->meta['url'] . "?host_id=" . config('api.888KING_HOST_ID_LIVE') . "&access_token=" . $response['data']['access_token'] . "&mode=singleplayer",
            // ];

            return [
                'url' => 'https://lobby.go888king.com/lobby/home?host_id=' . config('api.888KING_HOST_ID_LIVE') . '&access_token=' . $response['data']['access_token']
            ];
        }
    }

    public static function getBets($version_key = null, $isSkipStore = false)
    {
        if (!$version_key && !$isSkipStore) {
            $version_key = cache()->get('888king_version_key.' . config('api.888KING_HOST_ID_LIVE'), 0);
        }

        $response = _888kingController2::init("api/report", [
            'host_id' => config('api.888KING_HOST_ID_LIVE'),
            'key' => $version_key,
            'page_size' => 500,
        ]);

        if (!$response['status']) {
            return false;
        }

        if (!$isSkipStore) {
            if ($response['data']['key'] != 0) {
                cache()->put(
                    '888king_version_key.' . config('api.888KING_HOST_ID_LIVE'),
                    $response['data']['key']
                );

                GameLogKey::create([
                    'class' => _888king2::class,
                    'key' => $response['data']['key'],
                ]);
            }
        }


        return [
            "data" => $response['data']['report'],
            "key" => $version_key
        ];
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

    public static function randomPhone($requiredLength = 7, $highestDigit = 7)
    {
        $sequence = '';
        for ($i = 0; $i < $requiredLength; ++$i) {
            $sequence .= mt_rand(0, $highestDigit);
        }
        $numberPrefixes = ['011', '012', '013', '014', '016', '017', '018', '019'];
        for ($i = 0; $i < 21; ++$i) {
            $phone = $numberPrefixes[array_rand($numberPrefixes)] . $sequence;
        }
        return $phone;
    }
}
