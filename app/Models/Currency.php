<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Currency extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'key',
        'can_rebate',
        'can_commission',
        'require_verify',
    ];

    public function getUsernameAttribute()
    {
        return $this->code;
    }



    public function currency_products()
    {
        return $this->belongsToMany(Product::class, CurrencyProduct::class);
    }
}
