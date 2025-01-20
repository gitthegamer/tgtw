<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BetStake extends Model
{
    use HasFactory;

    protected $fillable = [
        'bet_id',
        'stake',
    ];
}
