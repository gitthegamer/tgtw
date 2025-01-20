<?php

namespace App\Models;

use App\Jobs\ProcessTransfer;
use App\Models\BindBonus;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class Member extends Authenticatable
{

    protected $fillable = [
        'master_id',
        'upline_id',
        'upline_type',
        'code',
        'currency',
        "rank_id",
        'point',
        'reward_point',
        'product_id',
        'wallet_type',
        'username',
        'password',
        'token',
        'balance',
        'firebase_token',
        'full_name',
        'email',
        'phone',
        'phone_verify',
        'remark',
        'status',
        'category',
        'language',
        'last_login_at',
        'register_from'
    ];

    protected $hidden = [
        'id',
        'product_id',
        'token',
    ];

    protected $casts = [
        'last_login_at' => "datetime",
        'rank_updated_at' => "datetime",
    ];

    public function getRouteKeyName()
    {
        return 'code';
    }

    public function member_accounts()
    {
        return $this->hasMany(MemberAccount::class);
    }


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function deposit()
    {
        $member = Member::find($this->id);
        if ($member->balance >= 1) {


            $transfer = Transfer::create([
                'uuid' => uniqid(),
                'product_id' => $member->product->id,
                'member_id' => $member->id,
                'type' => Transfer::TYPE_DEPOSIT,
                'amount' => $member->balance,
                'status' => Transfer::STATUS_IN_PROGRESS,
            ]);

            $member->decrement('balance', $transfer->amount);

            if ($member->product->deposit($member, $transfer) === false) {
                ProcessTransfer::dispatch($transfer);
                return false;
            }

            $transfer->update(['status' => Transfer::STATUS_SUCCESS]);
            return true;
        }
    }

    public function withdrawal()
    {
        
        $member = Member::find($this->id);
        $game_balance = $member->product->balance($member);

        Log::debug('game_balance: '.$game_balance);

        if ($game_balance && $game_balance >= 1) {
            $transfer = Transfer::create([
                'uuid' => uniqid(),
                'product_id' => $member->product->id,
                'member_id' => $member->id,
                'type' => Transfer::TYPE_WITHDRAWAL,
                'amount' => $game_balance,
                'status' => Transfer::STATUS_IN_PROGRESS,
            ]);

            if ($member->product->withdrawal($member, $transfer) === false) {
                ProcessTransfer::dispatch($transfer);
                return false;
            }

            $transfer->update(['status' => Transfer::STATUS_SUCCESS]);
            $member->increment('balance', $transfer->amount);
        }

        return true;
    }

    public function getData()
    {
        return [
            'balance' => round($this->balance ?? 0, 2),
            'balance_in_previous_game' => $this->getBalance() ?? 0,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'username' => $this->username,
            'code' => $this->code,
            'product' => Product::where('id', $this->product_id)->first()->name ?? null,
        ];
    }


    public function getToken()
    {
        if (!$this->token) {
            $this->update(['token' => $token = Str::uuid()]);
            return $token;
        }
        return $this->token;
    }

    public function getBalance()
    {
        $current_balance = $this->product ? $this->product->balance($this) : 0;

        return round($current_balance, 2);
    }

    public function launch($game, $isMobile)
    {
        $member = Member::find($this->id);
        if (!$member->product) {
            return false;
        }

        return $member->product->launch($member, $game, $isMobile);
    }
}
