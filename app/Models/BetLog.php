<?php

namespace App\Models;

use App\Jobs\SettleBetLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class BetLog extends Model
{
    const CATEGORY = [
        'SLOTS' => [
            "EGAMES",
        ],
        "LIVE_DEALER" => [
            "LIVECASINO",
        ],
        "SPORTSBOOK" => [
            "SPORT",
        ],
    ];

    protected $fillable = [
        'bet_id',
        'product',
        'game',
        'category',
        'username',
        'stake',
        'valid_stake',
        'payout',
        'winlose',
        'jackpot_win',
        'progressive_share',
        'payout_status',
        'bet_status',
        'account_date',
        'round_date',
        'modified_date',
        'round_at',
        'modified_at',
        'bet_detail',
        'is_settle',
        'key',
        'self_commission',
        'upline_commission',
        'master_commission',
    ];

    protected $casts = [
        'account_date' => 'date',
        'round_at' => 'datetime',
        'modified_at' => 'datetime',
        'bet_detail' => 'array',
    ];

    public function member()
    {
        return $this->belongsTo(
            Member::class,
            'username',
            'code'
        );
    }

    public function member_account()
    {
        return $this->belongsTo(MemberAccount::class, 'username', 'username');
    }

    public function product_object()
    {
        return $this->belongsTo(Product::class, 'product', 'code');
    }

    public function settle()
    {
        return !$this->is_settle ? SettleBetLog::dispatch($this) : false;
    }

    public function getCategory()
    {
        foreach (self::CATEGORY as $category => $values) {
            if (in_array($this->category, $values)) {
                return $category;
            }
        }
    }

    public function getRebate()
    {
        return $this->valid_stake * ($this->member->getRankSetting($this->getCategory() . '_rebate', 0) / 100);
    }

    public function getResult()
    {
        if ($this->product == "BIGGAMING" && $this->category == 1) {
            return view('admin.bet_details.BIGGAMING', [
                'details' => $this->bet_detail,
            ])->render();
        }
        return "";
    }

    public static function upsertByChunk($upserts)
    {
        $batchSize = 500;
        $retryAttempts = 3;
        $attempt = 1;

        while ($attempt <= $retryAttempts) {
            try {
                $chunks = array_chunk($upserts, $batchSize);
                foreach ($chunks as $chunk) {
                    BetLog::upsert($chunk, ['bet_id']);
                }
                break;
            } catch (Exception $e) {
                if (Str::contains($e->getMessage(), 'Deadlock found')) {
                    usleep(500000); // Sleep for 0.5 seconds
                }
            }
            $attempt++;
            if ($attempt > $retryAttempts) {
                break;
            }
        }
    }
}