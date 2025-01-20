<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    const STATUS_ACTIVE = 1, STATUS_NEW = 2, STATUS_HOT = 3, STATUS_MAINTENANCE = 4, STATUS_COMMING_SOON = 5, STATUS_POPULAR = 6;
    const STATUS = [
        self::STATUS_ACTIVE => "Active",
        self::STATUS_NEW => "New",
        self::STATUS_HOT => "Hot",
        self::STATUS_POPULAR => "Popular",
        self::STATUS_MAINTENANCE => "Maintenance",
        self::STATUS_COMMING_SOON => "Coming Soon",
    ];
    const CATEGORY_HIDE = 99, CATEGORY_LIVE = 1, CATEGORY_SPORTS = 2, CATEGORY_SLOTS = 3, CATEGORY_LOTTERY = 4, CATEGORY_APP = 5, CATEGORY_FISH = 6, CATEGORY_TABLE = 7, CATEGORY_EGAME = 8;

    const CATEGORY = [
        self::CATEGORY_HIDE => "Hide",
        self::CATEGORY_LIVE => "Live Dealer",
        self::CATEGORY_SPORTS => "Sportsbook",
        self::CATEGORY_SLOTS => "Slots",
        self::CATEGORY_LOTTERY => "Lottery",
        self::CATEGORY_APP => "App",
        self::CATEGORY_FISH => "Fishing",
        self::CATEGORY_TABLE => "Table Game",
        self::CATEGORY_EGAME => "EGame",
    ];
    const REBATE_CATEGORY = [
        self::CATEGORY_LIVE => "Live Dealer",
        self::CATEGORY_SPORTS => "Sportsbook",
        self::CATEGORY_SLOTS => "Slots",
    ];

    //FOR APP
    const THREE_GAME_LIST = 0, TWO_GAME_LIST = 1, LOTTERY_GAME = 2, WEBSITE_POPUP = 3;
    const ENABLE_TRUE = 1, ENABLE_FALSE = 0;

    const ERROR_INTERNAL_SYSTEM = 0,
        ERROR_PROVIDER_MAINTENANCE = 1,
        ERROR_CONNECTION_FAILED = 2,
        ERROR_TIMEOUT = 3,
        ERROR_ACCOUNT = 4,
        ERROR_UNKNOWN = 99;

    const ERROR_TYPE = [
        SELF::ERROR_INTERNAL_SYSTEM => "Internal System Error",
        SELF::ERROR_PROVIDER_MAINTENANCE => "Provider Maintenance",
        SELF::ERROR_CONNECTION_FAILED => "Connection Failed",
        SELF::ERROR_TIMEOUT => "Timeout",
        SELF::ERROR_ACCOUNT => "Account Error",
        SELF::ERROR_UNKNOWN => "Unknown Error",
    ];

    protected $fillable = [
        'sequence',
        'code',
        'name',
        'category',
        'class',
        'image',
        'status',
        'disclaimer',
        'web_download',
        'ios_download',
        'apk_download',
    ];

    protected $hidden = [
        'id',
        'type',
        'class',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'disclaimer' => 'array'
    ];

    protected $translatable = [
        'disclaimer',
    ];

    public function getRouteKeyName()
    {
        return 'code';
    }

    public static function fetch()
    {
        return Cache::rememberForever('products', function () {
            return Product::get();
        });
    }

    public function games()
    {
        return $this->hasMany(Game::class);
    }

    public function hasLobby()
    {
        return $this->games()->count();
    }

    public function isApp()
    {
        return $this->category == self::CATEGORY_APP;
    }

    public function getSlug()
    {
        if ($this->category == self::CATEGORY_LIVE) {
            return "LD";
        }
        if ($this->category == self::CATEGORY_SLOTS) {
            return "SL";
        }
        if ($this->category == self::CATEGORY_SPORTS) {
            return "SB";
        }
        if ($this->category == self::CATEGORY_FISH) {
            return "FH";
        }
        if ($this->category == self::CATEGORY_APP) {
            return "AP";
        }
        return "UK"; // unknown
    }



    public static function fetchLottoGameCode()
    {
        $product = Product::where('category', Product::CATEGORY_LOTTERY)->first();
        if (!$product) {
            return null;
        }
        return $product->code;
    }

    public function launch(Member $member, $game, $isMobile)
    {

        if (!$this->class::account($member)) {
            return false;
        }
        return $this->class::launch($member, $game, $isMobile);
    }

    public function balance(Member $member)
    {
        return $this->class::balance($member);
    }

    public function deposit(Member $member, Transfer $transfer)
    {
        return $this->class::deposit($member, $transfer);
    }

    public function withdrawal(Member $member, Transfer $transfer)
    {
        return $this->class::withdrawal($member, $transfer);
    }

    public function account_balance(MemberAccount $member_account)
    {
        return $this->class::account_balance($member_account);
    }

    public function account_deposit(MemberAccount $member_account, Transfer $transfer)
    {
        return $this->class::account_deposit($member_account, $transfer);
    }

    public function account_withdrawal(MemberAccount $member_account, Transfer $transfer)
    {
        return $this->class::account_withdrawal($member_account, $transfer);
    }

    public function checkTransaction(Transfer $transfer)
    {
        return $this->class::checkTransaction($transfer->uuid);
    }
}

