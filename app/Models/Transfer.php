<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transfer extends Model
{
    const TYPE_DEPOSIT = 1, TYPE_WITHDRAWAL = 2, TYPE_TRANSFER = 3;
    const STATUS_IN_PROGRESS = 1, STATUS_SUCCESS = 2, STATUS_FAIL = 3;

    protected $fillable = [
        'unique_id',
        'uuid',
        'product_id',
        'member_id',
        'type',
        'before_balance',
        'amount',
        'status',
        'message',
    ];

    protected $hidden = [
        'id',
        'unique_id',
        'product_id',
        'member_id',
    ];

    protected static function booted()
    {
        static::creating(function (Transfer $transfer) {
            $transfer->uuid = strtoupper(Str::random(8));
            $transfer->unique_id = SELF::generate_code();
        });
    }

    public static function generate_code()
    {
        while ($unique_id = strtoupper(Str::random(8))) {
            if (!Transfer::where("unique_id", $unique_id)->first()) {
                return $unique_id;
            }
        }
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
