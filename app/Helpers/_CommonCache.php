<?php

namespace App\Helpers;

use App\Models\Agent;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\ProductSetting;
use App\Models\Promotion;
use Illuminate\Support\Facades\Cache;

class _CommonCache
{
    public static function product_settings($code, $game = "")
    {
        if ($code === '28W') {
            $game = $game;
        } else {
            $game = "";
        }

        $product_settings = Cache::remember("product_settings_new_{$code}_{$game}", 60 * 60 * 24, function () use ($code, $game) {
            if ($code === '28W') {
                if ($game == _28Win::TYPE_豪龙 || $game == _28Win::TYPE_9_Lotto) {
                    return null;
                }
                return null;
            }
            return null;
        });


        return $product_settings;
    }

    public static function member_accounts($bet_log)
    {
        $member_account = Cache::remember(
            'member_account.' . $bet_log->username . "." . $bet_log->product . "." . $bet_log->category,
            60 * 60 * 24,
            function () use ($bet_log) {
                return MemberAccount::whereHas('product', function ($q) use ($bet_log) {
                    $q->where('code', $bet_log->product)->where('category', $bet_log->category);
                })->where('username', $bet_log->username)->first();
            }
        );

        return $member_account;
    }

    public static function member($id)
    {
        $member = Cache::remember(
            'member_cache.' . $id,
            60 * 60 * 24,
            function () use ($id) {
                return Member::find($id);
            }
        );

        return $member;
    }

    public static function promotion_item($id)
    {
        $promotion_item = Cache::remember("promotion_item.{$id}", 60 * 60 * 1, function () use ($id) {
            return Promotion::find($id);
        });

        return $promotion_item;
    }

    public static function product($id)
    {
        $product = Cache::remember("product_cache_.{$id}", 60 * 60 * 2, function () use ($id) {
            return Product::find($id);
        });

        return $product;
    }

    public static function product_code($code)
    {
        $product = Cache::remember("product_code_cache_.{$code}", 60 * 60 * 2, function () use ($code) {
            return Product::where('code', $code)->first();
        });

        return $product;
    }
}
