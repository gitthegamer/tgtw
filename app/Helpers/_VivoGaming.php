<?php

namespace App\Helpers;

use App\Models\Member;
use App\Models\Product;
use App\Models\Transfer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class _VivoGaming
{
    const VENDOR_ID = "fdqrgs4g6k", PREFIX = "sg4u_", OPERATOR_ID = "sg4u", VENDOR_MEMBER_ID = "";

    public static function create(Member $member)
    {
        return $member->member_accounts()->updateOrCreate([
            'member_id' => $member->id,
            'product_id' => $member->product_id,
        ], [
            'account' => $member->product->getSlug() . $member->code,
            'username' => $member->product->getSlug() . $member->code,
            'password' => Str::uuid()->toString(), // authenticate code
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

    public static function balance($member)
    {
        return 0;
    }

 public static function account(Member $member)
    {
        $account = SELF::check($member);
        return $account;
    }

    public static function withdrawal($member, $transfer)
    {
        $member->decrement('balance', $transfer->amount);
        return true;
    }

    public static function deposit($member, $transfer)
    {
        $member->increment('balance', $transfer->amount);
        return true;
    }


    public static function checkTransaction($uuid)
    {
        return [
            'status' => Transfer::STATUS_SUCCESS,
            'remark' => "Seamless Wallet Transfer Success",
        ];
    }

    public static function account_withdrawal($member_account, $transfer)
    {
        $member_account->member->decrement('balance', $transfer->amount);
        return true;
    }

    public static function account_deposit($member_account, $transfer)
    {
        $member_account->member->increment('balance', $transfer->amount);
        return true;
    }

    public static function account_balance($member_account)
    {
        return 0;
    }

    public static function launch(Member $member, $gameid = null, $isMobile = false, $blimit = null)
    {
        $member_account = SELF::check($member);
        if (!$member_account) {
            return Product::ERROR_ACCOUNT;
        }

        $token = Str::uuid()->toString();
        $member_account->update(['password' => $token]);
       
        // return [
        //     'url' => 'https://games.vivogaming.com/lobby/?token='.$token.'&operatorID='.SELF::OPERATOR_ID.'&application=lobby'
        // ];
    }

    public function updateStatus(Member $member)
    {
        return true;
    }

    public static function getBets()
    {
        return [];
    }
}
