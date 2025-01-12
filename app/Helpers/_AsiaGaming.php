<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_AsiaGamingController;

class _AsiaGaming
{
    public static function create(Member $member)
    {
        $password = null;

        $response = _AsiaGamingController::init("lg", [
            'cagent' => config('api.AG_CAGENT'),
            'loginname' => $member->code,
            'method' => "lg",
            'actype' => config('api.AG_ACTYPE'),
            'oddtype' => config('api.AG_BET_LIMIT'),
            'password' => $password = SELF::randomPassword(),
            'cur' => "MYR",
        ]);

        if (!$response['status']) {
            return false;
        }

        if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
            return $member->member_accounts()->updateOrCreate([
                'member_id' => $member->id,
                'product_id' => $member->product_id,
            ], [
                'account' => $member->code,
                'username' => $member->code,
                'password' => $password,
            ]);
        }

        return false;
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
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $response = _AsiaGamingController::init("gb", [
            'cagent' => config('api.AG_CAGENT'),
            'loginname' => $member_account->username,
            'method' => "gb",
            'actype' => config('api.AG_ACTYPE'),
            'password' => $member_account->password,
            'cur' => "MYR",
        ]);

        if (!$response['status']) {
            return false;
        }

        if (isset($response['data']['@attributes']['info'])) {
            return $response['data']['@attributes']['info'];
        }

        return false;
    }

    public static function deposit($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $billno = self::generateBillNo();

        $response = _AsiaGamingController::init("tc", [
            'cagent' => config('api.AG_CAGENT'),
            'loginname' => $member_account->username,
            'method' => "tc",
            'billno' => $billno,
            'type' => "IN",
            'credit' => $transfer->amount,
            'actype' => config('api.AG_ACTYPE'),
            'password' => $member_account->password,
            'cur' => "MYR",
        ]);

        if (!$response['status'] || $response['data']['@attributes']['info'] !== "0") {
            return false;
        }

        if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
            $flag = 1;

            $response = _AsiaGamingController::init("tcc", [
                'cagent' => config('api.AG_CAGENT'),
                'loginname' => $member_account->username,
                'method' => "tcc",
                'billno' => $billno,
                'type' => "IN",
                'credit' => $transfer->amount,
                'flag' => $flag,
                'actype' => config('api.AG_ACTYPE'),
                'password' => $member_account->password,
                'cur' => "MYR",
            ]);

            if (!$response['status'] || $response['data']['@attributes']['info'] !== "0") {
                return false;
            }

            if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                $maxRetries = 10;
                $retryCount = 0;

                while ($retryCount < $maxRetries) {
                    $response = _AsiaGamingController::init("qos", [
                        'cagent' => config('api.AG_CAGENT'),
                        'method' => "qos",
                        'billno' => $billno,
                        'actype' => config('api.AG_ACTYPE'),
                        'cur' => "MYR",
                    ]);

                    if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                        return true;
                    } elseif (
                        stripos($response['data']['@attributes']['info'], 'network_error') !== false ||
                        stripos($response['data']['@attributes']['info'], 'network error') !== false ||
                        stripos($response['data']['@attributes']['info'], '1') !== false
                    ) {
                        $retryCount++;
                        continue;
                    } else {
                        return false;
                    }
                }
            } else {
                if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                    return true;
                }
            }
        }

        return false;
    }

    public static function withdrawal($member, $transfer)
    {
        $member_account = SELF::account($member);
        if (!$member_account) {
            return false;
        }

        $billno = self::generateBillNo();

        $response = _AsiaGamingController::init("tc", [
            'cagent' => config('api.AG_CAGENT'),
            'loginname' => $member_account->username,
            'method' => "tc",
            'billno' => $billno,
            'type' => "OUT",
            'credit' => $transfer->amount,
            'actype' => config('api.AG_ACTYPE'),
            'password' => $member_account->password,
            'cur' => "MYR",
        ]);

        if (!$response['status'] || $response['data']['@attributes']['info'] !== "0") {
            return false;
        }

        if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
            $flag = 1;

            $response = _AsiaGamingController::init("tcc", [
                'cagent' => config('api.AG_CAGENT'),
                'loginname' => $member_account->username,
                'method' => "tcc",
                'billno' => $billno,
                'type' => "OUT",
                'credit' => $transfer->amount,
                'flag' => $flag,
                'actype' => config('api.AG_ACTYPE'),
                'password' => $member_account->password,
                'cur' => "MYR",
            ]);

            if (!$response['status'] || $response['data']['@attributes']['info'] !== "0") {
                return false;
            }

            if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                $maxRetries = 10;
                $retryCount = 0;

                while ($retryCount < $maxRetries) {
                    $response = _AsiaGamingController::init("qos", [
                        'cagent' => config('api.AG_CAGENT'),
                        'method' => "qos",
                        'billno' => $billno,
                        'actype' => config('api.AG_ACTYPE'),
                        'cur' => "MYR",
                    ]);

                    if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                        return true;
                    } elseif (
                        stripos($response['data']['@attributes']['info'], 'network_error') !== false ||
                        stripos($response['data']['@attributes']['info'], 'network error') !== false ||
                        stripos($response['data']['@attributes']['info'], '1') !== false
                    ) {
                        $retryCount++;
                        continue;
                    } else {
                        return false;
                    }
                }
            } else {
                if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                    return true;
                }
            }
        }

        return false;
    }

    public static function account_balance($member_account)
    {
        $response = _AsiaGamingController::init("gb", [
            'cagent' => config('api.AG_CAGENT'),
            'loginname' => $member_account->username,
            'method' => "gb",
            'actype' => config('api.AG_ACTYPE'),
            'password' => $member_account->password,
            'cur' => "MYR",
        ]);

        if (!$response['status']) {
            return false;
        }

        if (isset($response['data']['@attributes']['info'])) {
            return $response['data']['@attributes']['info'];
        }

        return false;
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $billno = self::generateBillNo();

        $response = _AsiaGamingController::init("tc", [
            'cagent' => config('api.AG_CAGENT'),
            'loginname' => $member_account->username,
            'method' => "tc",
            'billno' => $billno,
            'type' => "OUT",
            'credit' => $transfer->amount,
            'actype' => config('api.AG_ACTYPE'),
            'password' => $member_account->password,
            'cur' => "MYR",
        ]);

        if (!$response['status'] || $response['data']['@attributes']['info'] !== "0") {
            return false;
        }

        if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
            $flag = 1;

            $response = _AsiaGamingController::init("tcc", [
                'cagent' => config('api.AG_CAGENT'),
                'loginname' => $member_account->username,
                'method' => "tcc",
                'billno' => $billno,
                'type' => "OUT",
                'credit' => $transfer->amount,
                'flag' => $flag,
                'actype' => config('api.AG_ACTYPE'),
                'password' => $member_account->password,
                'cur' => "MYR",
            ]);

            if (!$response['status'] || $response['data']['@attributes']['info'] !== "0") {
                return false;
            }

            if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                $maxRetries = 10;
                $retryCount = 0;

                while ($retryCount < $maxRetries) {
                    $response = _AsiaGamingController::init("qos", [
                        'cagent' => config('api.AG_CAGENT'),
                        'method' => "qos",
                        'billno' => $billno,
                        'actype' => config('api.AG_ACTYPE'),
                        'cur' => "MYR",
                    ]);

                    if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                        return true;
                    } elseif (
                        stripos($response['data']['@attributes']['info'], 'network_error') !== false ||
                        stripos($response['data']['@attributes']['info'], 'network error') !== false ||
                        stripos($response['data']['@attributes']['info'], '1') !== false
                    ) {
                        $retryCount++;
                        continue;
                    } else {
                        return false;
                    }
                }
            } else {
                if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                    return true;
                }
            }
        }

        return false;
    }

    public static function account_deposit($member_account, $transfer)
    {
        $billno = self::generateBillNo();

        $response = _AsiaGamingController::init("tc", [
            'cagent' => config('api.AG_CAGENT'),
            'loginname' => $member_account->username,
            'method' => "tc",
            'billno' => $billno,
            'type' => "IN",
            'credit' => $transfer->amount,
            'actype' => config('api.AG_ACTYPE'),
            'password' => $member_account->password,
            'cur' => "MYR",
        ]);

        if (!$response['status'] || $response['data']['@attributes']['info'] !== "0") {
            return false;
        }

        if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
            $flag = 1;

            $response = _AsiaGamingController::init("tcc", [
                'cagent' => config('api.AG_CAGENT'),
                'loginname' => $member_account->username,
                'method' => "tcc",
                'billno' => $billno,
                'type' => "IN",
                'credit' => $transfer->amount,
                'flag' => $flag,
                'actype' => config('api.AG_ACTYPE'),
                'password' => $member_account->password,
                'cur' => "MYR",
            ]);

            if (!$response['status'] || $response['data']['@attributes']['info'] !== "0") {
                return false;
            }

            if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                $maxRetries = 10;
                $retryCount = 0;

                while ($retryCount < $maxRetries) {
                    $response = _AsiaGamingController::init("qos", [
                        'cagent' => config('api.AG_CAGENT'),
                        'method' => "qos",
                        'billno' => $billno,
                        'actype' => config('api.AG_ACTYPE'),
                        'cur' => "MYR",
                    ]);

                    if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                        return true;
                    } elseif (
                        stripos($response['data']['@attributes']['info'], 'network_error') !== false ||
                        stripos($response['data']['@attributes']['info'], 'network error') !== false ||
                        stripos($response['data']['@attributes']['info'], '1') !== false
                    ) {
                        $retryCount++;
                        continue;
                    } else {
                        return false;
                    }
                }
            } else {
                if (isset($response['data']['@attributes']['info']) && $response['data']['@attributes']['info'] === "0") {
                    return true;
                }
            }
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

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => 'AG Transaction TEST',
        ];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $sid = self::generateSid();

        $bet_limit = SELF::bet_limit($member_account, $member);

        $response = _AsiaGamingController::init("login", [
            'cagent' => config('api.AG_CAGENT'),
            'loginname' => $member_account->username,
            'actype' => config('api.AG_ACTYPE'),
            'password' => $member_account->password,
            'sid' => $sid,
            'lang' => _AsiaGamingController::getLocale(),
            'gameType' => 0,
            'oddtype' => $bet_limit,
            'cur' => "MYR",
        ]);

        if (!$response['status']) {
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }

        if ($response['status'] && isset($response['data']) && !empty($response['data'])) {
            return [
                'url' => $response['data']
            ];
            return $response['data'];
        }

        return Product::ERROR_INTERNAL_SYSTEM;
    }

    public static function bet_limit($member_account, $member)
    {
        $bet_limit = $member->getBetLimit();
        if (!$bet_limit) {
            return;
        }

        return $bet_limit['code'] ?? config('api.AG_BET_LIMIT') ?? null;
    }

    public static function getTimestamp()
    {
        return time();
    }

    public static function randomPassword($len = 8)
    {
        $len = max(6, min(12, $len));

        $sets = array();
        $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        $sets[] = '123456789';

        $password = '';

        foreach ($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
        }

        while (strlen($password) < $len) {
            $randomSet = $sets[array_rand($sets)];
            $password .= $randomSet[array_rand(str_split($randomSet))];
        }

        return str_shuffle($password);
    }

    public static function getBets($startDate, $endDate, $page = 1)
    {
        $allBets = [];
        $keepFetching = true;

        while ($keepFetching) {
            $response = _AsiaGamingController::init("betlog", [
                'cagent' => config('api.AG_REPORT_CAGENT'),
                'startdate' => $startDate,
                'enddate' => $endDate,
                'page' => $page,
            ]);

            if (!$response['status']) {
                return false;
            }

            if (isset($response['data']['row'])) {
                $betsData = $response['data']['row'];

                if (array_key_exists('@attributes', $betsData)) {
                    $betsData = [$betsData];
                }

                foreach ($betsData as $bet) {
                    if (isset($bet['@attributes'])) {
                        $allBets[] = $bet['@attributes'];
                    }
                }
            }

            $keepFetching = $page < $response['data']['addition']['totalpage'];
            $page++;
        }
        return $allBets;
    }

    static function generateBillNo()
    {
        return config('api.AG_CAGENT') . substr(md5(uniqid(mt_rand(), true)), 0, mt_rand(13, 16));
    }

    static function generateSid()
    {
        return config('api.AG_CAGENT') . substr(md5(uniqid(mt_rand(), true)), 0, mt_rand(13, 16));
    }
}
