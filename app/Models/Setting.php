<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'name',
        'value',
    ];

    protected static function booted()
    {
        static::saved(function ($setting) {
            Cache::forget('settings.' . $setting->name);
        });
    }

    public static function get($name, $default = null)
    {
        return Cache::rememberForever('settings.' . $name, function () use ($name, $default) {
            $setting = Setting::where('name', $name)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public function getValueAttribute($value)
    {
        return json_decode($value, true) ? json_decode($value, true) : $value;
    }

    public function setValueAttribute($value)
    {
        Cache::put('settings.' . $this->name, $value);
        $this->attributes['value'] = $value;
    }
}
