<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\BackupAccounts;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use App\Modules\_28WINController;
use Carbon\Carbon;

class _28Win
{
    public $operator;
    public $product;
    public $apiUser;
    public $apiPass;
    public $user;
    public $pass;

    const TYPE_万能 = 1, TYPE_跑马 = 2, TYPE_多多 = 3, TYPE_新加坡 = 4, TYPE_沙巴 = 5, TYPE_砂老越 = 6, TYPE_Unkown = 7, TYPE_豪龙 = 8,  TYPE_9_Lotto = 9, TYPES = [
        1 => '万能',
        2 => '跑马',
        3 => '多多',
        4 => '新加坡',
        5 => '沙巴',
        6 => '砂老越',
        7 => 'Unknown',
        8 => '豪龙',
        9 => '9 Lotto',
    ];

    public static function create(Member $member, $product_id = null)
    {
        $start_time = Carbon::createFromFormat('H:i a', '03:00 PM');
        $end_time = Carbon::createFromFormat('H:i a', '09:00 PM');
        $block_time = Carbon::now()->between($start_time, $end_time, true);
        $login_password = SELF::randomPassword();

        if ($block_time && $product_id != null) {
            return false;
        }

        $response = _28WINController::init("createPlayer.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => config('api.WIN28_USER'),
            'pass' => config('api.WIN28_PASS'),
            'loginID' => strtolower($member->code),
            'loginPass' => $login_password,
            'fullName' => strtolower(str_replace(' ', '', $member->name ?? _28Win::randomAlphabeticString(10))),
        ]);

        if ($response['status'] === false) {
            if ($product_id != null) { // * mean is kernel auto create
                return false;
            }

            $product = _CommonCache::product($member->product_id);
            if (!$product) {
                return false;
            }

            $account = $member->member_accounts()->where('member_id', $member->id)->where('product_id', $product_id ? $product_id : $member->product_id)->first();
            if ($account) {
                return false;
            }

            // to ensure there's a backup account
            $backup_account = BackupAccounts::where('status', BackupAccounts::STATUS_UNCLAIMED)
                ->where('code', $product->code)
                ->first();

            if (!$backup_account) {
                return false;
            }
            $backup_account->update(['status' => BackupAccounts::STATUS_CLAIMED]);



            return $member->member_accounts()->updateOrCreate([
                'member_id' => $member->id,
                'product_id' => $product_id ? $product_id : $member->product_id,
            ], [
                'account' => $backup_account->username,
                'username' => $backup_account->username,
                'password' => $backup_account->password,
            ]);
        }

        if ($response['data']['errorCode'] == "4") {
            $response = _28WINController::init("changePasswordStatus.aspx", [
                'apiUser' => config('api.WIN28_API_USER'),
                'apiPass' => config('api.WIN28_API_PASS'),
                'user' => config('api.WIN28_USER'),
                'pass' => config('api.WIN28_PASS'),
                'loginID' => strtolower($member->code),
                'newpass' => $login_password,
            ]);

            return $member->member_accounts()->updateOrCreate([
                'member_id' => $member->id,
                'product_id' => $product_id ? $product_id : $member->product_id,
            ], [
                'account' => strtolower($member->code),
                'username' => strtolower($member->code),
                'password' => $login_password,
            ]);
        }
        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $product_id ? $product_id : $member->product_id,
        ], [
            'account' => strtolower($member->code),
            'username' => strtolower($member->code),
            'password' => $login_password,
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
        $response = _28WINController::init("getProfile.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => $member_account->username,
            'pass' => $member_account->password,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        if (isset($response['data']['balance'])) {
            $cleanedString = str_replace(",", "", $response['data']['balance']);
            return $cleanedString;
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

            $balance = $member_account->balance();

            $transfer->update(['before_balance' => $balance]);

            $response = _28WINController::init("getProfile.aspx", [
                'apiUser' => config('api.WIN28_API_USER'),
                'apiPass' => config('api.WIN28_API_PASS'),
                'user' => config('api.WIN28_USER'),
                'pass' => config('api.WIN28_PASS'),
            ]);
            if ($response['status'] == false) {
                return false;
            }

            $response = _28WINController::init("deposit.aspx", [
                'apiUser' => config('api.WIN28_API_USER'),
                'apiPass' => config('api.WIN28_API_PASS'),
                'user' => config('api.WIN28_USER'),
                'pass' => config('api.WIN28_PASS'),
                'loginID' => $member_account->username,
                'amount' => $transfer->amount,
            ]);

            if ($response['status'] == false) {
                return false;
            }
        }

        return true;
    }

    public static function account_deposit($member_account, $transfer)
    {
        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _28WINController::init("getProfile.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => config('api.WIN28_USER'),
            'pass' => config('api.WIN28_PASS'),
        ]);

        if ($response['status'] == false) {
            return false;
        }

        $response = _28WINController::init("deposit.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => config('api.WIN28_USER'),
            'pass' => config('api.WIN28_PASS'),
            'loginID' => $member_account->username,
            'amount' => $transfer->amount,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return true;
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
        $balance = $member_account->balance();

        $transfer->update(['before_balance' => $balance]);

        $response = _28WINController::init("getProfile.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => config('api.WIN28_USER'),
            'pass' => config('api.WIN28_PASS'),
        ]);

        if ($response['status'] == false) {
            return false;
        }

        $response = _28WINController::init("withdraw.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => config('api.WIN28_USER'),
            'pass' => config('api.WIN28_PASS'),
            'loginID' => $member_account->username,
            'amount' => $transfer->amount,
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

        $member_account = SELF::check($transfer->member);
        if (!$member_account) {
            return false;
        }

        $current_balance = $member_account->balance();

        if (($transfer->before_balance === 0) && ($current_balance === 0)) {
            return [
                'status' => Transfer::STATUS_SUCCESS,
                'remark' => 'manual check',
            ];
        }
        if ($transfer->before_balance === $current_balance) {
            return [
                'status' => Transfer::STATUS_FAIL,
                'remark' => 'manual check',
            ];
        }

        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => 'manual check',
        ];
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);

        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $launch = _28WINController::init("betLogin.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => $member_account->username,
            'pass' => $member_account->password,
        ]);



        if ($launch['status'] == false) {
            if (isset($launch['data']['errorCode']) && $launch['data']['errorCode'] == 2) {
                $member_account->delete();
                return SELF::launch($member, $gameid, $isMobile, $blimit);
            }
            return Product::ERROR_PROVIDER_MAINTENANCE;
        }


        return [
            'url' => _28WINController::generateLogin(strtolower($member_account->username), $launch['data']['sessionID'], $launch['data']['tokenCode'], $isMobile)
        ];
    }

    public static function betListPage($date, $page = 1)
    {
        $response = _28WINController::init("betlistPage.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => config('api.WIN28_USER'),
            'pass' => config('api.WIN28_PASS'),
            'dateFrom' => Carbon::parse($date)->copy()->format('Y-m-d'),
            'dateTo' => Carbon::parse($date)->copy()->addDay()->format('Y-m-d'),
            'page' => $page,
        ]);

        if ($response['status'] == false) {
            return [];
        }

        if ($response['status']) {
            return array_merge($response['data']['betData'], SELF::betListPage($date, $page + 1));
        }

        return $response['data']['betData'];
    }

    public static function winLoss($drawType, $date)
    {
        $response = _28WINController::init("winLoss.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => config('api.WIN28_USER'),
            'pass' => config('api.WIN28_PASS'),
            'dateFrom' => Carbon::parse($date)->copy()->format('Y-m-d'),
            'dateTo' => Carbon::parse($date)->copy()->format('Y-m-d'),
            'currency' => config('api.WIN28_CURRENCY'),
            'drawType' => $drawType,
        ]);

        if ($response['status'] == false) {
            return [];
        }

        return $response['data'];
    }

    public static function winNumber($date, $username, $password)
    {

        $response = _28WINController::init("winNumber.aspx", [
            'apiUser' => config('api.WIN28_API_USER'),
            'apiPass' => config('api.WIN28_API_PASS'),
            'user' => strtolower($username),
            'pass' => $password,
            'dateFrom' => $date,
            'dateTo' => $date,
        ]);

        if ($response['status'] == false) {
            return false;
        }

        return $response['data'];
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

    public static function randomAlphabeticString($len = 8)
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz';
        $string = '';

        while (strlen($string) < $len) {
            $string .= $alphabet[array_rand(str_split($alphabet))];
        }

        return $string;
    }

    /**
     * Generate a full name for the member, using only alphabetic characters.
     * If the member's name is not provided, generate a random alphabetic string.
     *
     * @param Member $member The member object.
     * @return string The generated full name or a random alphabetic string.
     */
    public static function generateFullName(Member $member)
    {
        if (empty($member->name)) {
            // Generate a random alphabetic string if the member's name is empty
            return self::randomAlphabeticString(8); // You can adjust the length as needed
        }

        // Remove any non-alphabetic characters and convert to lowercase
        $cleanName = preg_replace('/[^a-zA-Z]/', '', $member->name);
        return strtolower($cleanName);
    }
}
