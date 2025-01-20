<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyProduct extends Model
{
    protected $fillable = [
        'currency_id',
        'product_id',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
