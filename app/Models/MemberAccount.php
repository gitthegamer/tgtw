<?php

namespace App\Models;

use App\Crud\Buttons\TextButton;
use Illuminate\Database\Eloquent\Model;
use App\Crud\Outputs\Ajax;
use App\Crud\Outputs\Datatables;
use App\Jobs\ProcessTransfer;

class MemberAccount extends Model
{
    protected $fillable = [
        'member_id',
        'product_id',
        'account',
        'username',
        'password',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function balance()
    {
        return $this->product->account_balance($this);
    }

    public function withdrawalNow()
    {
        $game_balance = $this->product->account_balance($this);

        if ($game_balance != false && $game_balance >= 1) {
            $transfer = Transfer::create([
                'product_id' => $this->product->id,
                'member_id' => $this->member->id,
                'type' => Transfer::TYPE_WITHDRAWAL,
                'amount' => $game_balance,
                'status' => Transfer::STATUS_IN_PROGRESS,
            ]);

            if (!$this->product->account_withdrawal($this, $transfer)) {
                ProcessTransfer::dispatchSync($transfer);
                return false;
            }

            $this->member->increment('balance', $transfer->amount);
            $transfer->update(['status' => Transfer::STATUS_SUCCESS]);
            return true;
        }

        return false;
    }
    
    public function withdrawal()
    {
        $game_balance = $this->product->account_balance($this);

        if ($game_balance != false && $game_balance >= 1) {
            $transfer = Transfer::create([
                'product_id' => $this->product->id,
                'member_id' => $this->member->id,
                'type' => Transfer::TYPE_WITHDRAWAL,
                'amount' => $game_balance,
                'status' => Transfer::STATUS_IN_PROGRESS,
            ]);

            if (!$this->product->account_withdrawal($this, $transfer)) {
                ProcessTransfer::dispatch($transfer);
                return false;
            }

            $this->member->increment('balance', $transfer->amount);
            $transfer->update(['status' => Transfer::STATUS_SUCCESS]);
            return true;
        }

        return false;
    }

    public static function makeTable($query)
    {
        return DataTables::make($query)
            ->setColumns(
                [
                    'product' => function ($data) {
                        return $data->product->name;
                    }, 'category' => function ($data) {
                        return $data->product::CATEGORY[$data->product->category];
                    }, 'username' => function ($data) {
                        return $data->username;
                    }, 'password' => function ($data) {
                        return $data->password;
                    }, 'balance' => function ($data) {
                        return Ajax::make(route('admin.member.member_account.balance', ['member_account' => $data]))->render();
                    },
                    '' => function ($data) {
                        $output = "";
                        $output = TextButton::make("Recall", "btn btn-xs btn-warning", route('admin.member.member_account.recall', ['member_account' => $data]))->render();
                        return $output;
                    }
                ]
            )->setAlign("table-center");
    }
}
