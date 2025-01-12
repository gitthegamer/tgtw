<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use App\Models\Member;

class Affiliate
{
    public static function generate_code()
    {
        while ($code = strtoupper(Str::random(6))) {
            if (!Affiliate::validate_code($code)) {
                return $code;
            }
        }
        
        return null;
    }

    public static function validate_code($value)
    {
        return (Member::where('code', $value)->count() ? true : false);
    }

    public static function find_upline($value)
    {
        return Member::where('code', $value)->first();
    }
}
