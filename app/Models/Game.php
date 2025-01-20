<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Game extends Model
{
    const LABEL_NONE = 0, LABEL_NEW = 1, LABEL_POPULAR = 2, LABEL_OTHER = 3, LABEL_MAINTENANCE = 4, LABEL_HOT = 5, LABEL_RECOMMENDED = 6,LABEL = [
        SELF::LABEL_NONE => "None",
        SELF::LABEL_NEW => "New",
        SELF::LABEL_HOT => "Hot",
        SELF::LABEL_POPULAR => "Popular",
        SELF::LABEL_OTHER => "Other",
        SELF::LABEL_MAINTENANCE => "Maintenance",
        SELF::LABEL_RECOMMENDED => "Recommended",
    ];

    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'product_id',
        'code',
        'name',
        'image',
        'label',
        'meta',
    ];

    public $translatable = [
        'name',
    ];

    public $casts = [
        'meta' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
