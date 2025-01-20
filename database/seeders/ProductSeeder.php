<?php

namespace Database\Seeders;

use App\Helpers\_3Win8;
use App\Helpers\_888king2;
use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_918kiss2;
use App\Helpers\_998Bet;
use App\Helpers\_9WicKets;
use App\Helpers\_ACE333;
use App\Helpers\_AceWin;
use App\Helpers\_AdvantPlay;
use App\Helpers\_AECasino;
use App\Helpers\_Allbet;
use App\Helpers\_AsiaGaming;
use App\Helpers\_AstarCasino;
use App\Helpers\_Avatar;
use App\Helpers\_BTI;
use App\Helpers\_CMD368;
use App\Helpers\_CQ9;
use App\Helpers\_DigMan;
use App\Helpers\_DragonGaming;
use App\Helpers\_Dragonsoft;
use App\Helpers\_Dreaming;
use App\Helpers\_E1Sport;
use App\Helpers\_Evo888;
use App\Helpers\_Evolution;
use App\Helpers\_Ezugi;
use App\Helpers\_FaChai;
use App\Helpers\_FunkyGames;
use App\Helpers\_GrandLotto;
use App\Helpers\_GW99;
use App\Helpers\_HGClub;
use App\Helpers\_HotRoad;
use App\Helpers\_JDBGaming;
use App\Helpers\_Jili;
use App\Helpers\_King855;
use App\Helpers\_Lionking;
use App\Helpers\_Live22;
use App\Helpers\_Lucky365;
use App\Helpers\_M8;
use App\Helpers\_MD368;
use App\Helpers\_MegaPlus;
use App\Helpers\_MonkeyKing;
use App\Helpers\_Netent;
use App\Helpers\_NextSpin;
use App\Helpers\_Obet33;
use App\Helpers\_PGS;
use App\Helpers\_Play8;
use App\Helpers\_Playboy;
use App\Helpers\_PlayNGo;
use App\Helpers\_PlayStar;
use App\Helpers\_PokerWin;
use App\Helpers\_PP;
use App\Helpers\_QB838;
use App\Helpers\_QQKeno;
use App\Helpers\_RedTiger;
use App\Helpers\_RelaxGaming;
use App\Helpers\_SAGaming;
use App\Helpers\_Sexybrct;
use App\Helpers\_Sky1388;
use App\Helpers\_SpadeGaming;
use App\Helpers\_Spinix;
use App\Helpers\_CrowdPlay;
use App\Helpers\_Sportsbook;
use App\Helpers\_SunCity;
use App\Helpers\_SV388;
use App\Helpers\_TFGaming;
use App\Helpers\_UUSlot;
use App\Helpers\_VivoGaming;
use App\Helpers\_Vpower;
use App\Helpers\_WBet;
use App\Helpers\_WCasino;
use App\Helpers\_WMCasino;
use App\Helpers\_XE88;
use App\Helpers\_XPoker;
use App\Helpers\_YesGetRich;
use App\Helpers\_MegaH5;
use App\Helpers\_MK;
use App\Helpers\_HoGaming;
use App\Helpers\_FFF;
use App\Helpers\_Revolution;
use App\Helpers\_YellowBat;
use App\Helpers\_Rich88;
use App\Helpers\_Spribe;
use App\Helpers\_Apollo;
use App\Helpers\BG;
use App\Helpers\IBC;
use App\Helpers\Joker;
use App\Helpers\Mega888;
use App\Helpers\Playtech;
use App\Helpers\Pussy888;
use App\Models\CurrencyProduct;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $productsData = [
            // ['code' => '8K', 'name' => '888King', 'class' => _888king2::class],
            // ['code' => 'JK', 'name' => 'Joker', 'class' => Joker::class],
            // ['code' => 'DS', 'name' => 'DragonSoft', 'class' => _Dragonsoft::class],
            // ['code' => 'LK', 'name' => 'Lion King', 'class' => _Lionking::class, 'apk_download' => 'https://dl.lk4u.xyz'],
            // ['code' => 'L365', 'name' => 'Lucky365', 'class' => _Lucky365::class],
            // ['code' => 'VP', 'name' => 'Vpower', 'class' => _Vpower::class],
            // ['code' => 'PGS', 'name' => 'PGS', 'class' => _PGS::class],
            // ['code' => 'PP', 'name' => 'Pragmatic Play', 'class' => _PP::class],
            // ['code' => 'L2', 'name' => 'Live22', 'class' => _Live22::class],
            // ['code' => 'AP', 'name' => 'AdvantPlay', 'class' => _AdvantPlay::class],
            // ['code' => 'GW99', 'name' => 'GreatWall99', 'class' => _GW99::class],
            // ['code' => 'XE88', 'name' => 'XE88', 'class' => _XE88::class],
            // ['code' => '3WIN8', 'name' => '3Win8', 'class' => _3Win8::class],
            // ['code' => 'ACE333', 'name' => 'Ace333', 'class' => _ACE333::class],
            // ['code' => '918KAYA', 'name' => '918kaya', 'class' => _918kaya::class],
            // ['code' => 'JILI', 'name' => 'Jili', 'class' => _Jili::class],
            // ['code' => 'SPADEGAMING', 'name' => 'Spade Gaming', 'class' => _SpadeGaming::class],
            // ['code' => 'NEXTSPIN', 'name' => 'Next Spin', 'class' => _NextSpin::class],
            // ['code' => 'NETENT', 'name' => 'Netent', 'class' => _Netent::class],
            // ['code' => 'REDTIGER', 'name' => 'Red Tiger', 'class' => _RedTiger::class],
            // ['code' => 'CQ9', 'name' => 'CQ9', 'class' => _CQ9::class],
            // ['code' => 'SPINIX', 'name' => 'Spinix', 'class' => _Spinix::class],
            // ['code' => 'FACHAI', 'name' => 'Fa Chai', 'class' => _FaChai::class],
            // ['code' => 'YGR', 'name' => 'Yes Get Rich', 'class' => _YesGetRich::class],
            // ['code' => 'FUNKYGAMES', 'name' => 'Funky Games', 'class' => _FunkyGames::class],
            // ['code' => 'JDBGAMING', 'name' => 'JDB Gaming', 'class' => _JDBGaming::class],
            // ['code' => 'RELAXGAMING', 'name' => 'Relax Gaming', 'class' => _RelaxGaming::class],
            // ['code' => 'PLAYSTAR', 'name' => 'Play Star', 'class' => _PlayStar::class],
            // ['code' => 'MEGAPLUS', 'name' => 'Mega Plus', 'class' => _MegaPlus::class],
            // ['code' => 'UUSLOT', 'name' => 'UU Slot', 'class' => _UUSlot::class],
            // ['code' => 'ACEWIN', 'name' => 'Ace Win', 'class' => _AceWin::class],
            // ['code' => 'PLAY8', 'name' => 'Play 8', 'class' => _Play8::class],
            // ['code' => 'SV388', 'name' => 'SV388', 'class' => _SV388::class],
            // ['code' => 'HOTROAD', 'name' => 'Hot Road', 'class' => _HotRoad::class],
            // ['code' => 'MEGAH5', 'name' => 'MegaH5', 'class' => _MegaH5::class],
            // ['code' => 'MK', 'name' => 'Monkey King', 'class' => _MK::class],
            // ['code' => 'MONKEYKING', 'name' => 'Monkey King', 'class' => _MonkeyKing::class],
            // ['code' => 'AVATAR', 'name' => 'Avatar', 'class' => _Avatar::class],
            // ['code' => 'SKY1388', 'name' => 'Sky1388', 'class' => _Sky1388::class],
            // ['code' => 'DRAGONGAMING', 'name' => 'Dragon Gaming', 'class' => _DragonGaming::class],
            // ['code' => 'PLAYNGO', 'name' => 'Play N Go', 'class' => _PlayNGo::class],
            // ['code' => 'PTS', 'name' => 'Playtech', 'class' => Playtech::class],
            // ['code' => 'PTL', 'name' => 'Playtech', 'class' => Playtech::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'BG', 'name' => 'Big Gaming', 'class' => BG::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'VG', 'name' => 'Vivo Gaming', 'class' => _VivoGaming::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'EVO', 'name' => 'Evolution', 'class' => _Evolution::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'AB', 'name' => 'AllBet', 'class' => _Allbet::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'DG', 'name' => 'Dream Gaming', 'class' => _Dreaming::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'SEXYBCRT', 'name' => 'Sexy Baccarat', 'class' => _Sexybrct::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'K855', 'name' => 'King855', 'class' => _King855::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'AG', 'name' => 'Asia Gaming', 'class' => _AsiaGaming::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'AECASINO', 'name' => 'AE Casino', 'class' => _AECasino::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'ASCASINO', 'name' => 'Astar Casino', 'class' => _AstarCasino::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'SAGAMING', 'name' => 'SA Gaming', 'class' => _SAGaming::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'WMCASINO', 'name' => 'WM Casino', 'class' => _WMCasino::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'WCASINO', 'name' => 'W Casino', 'class' => _WCasino::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'EZUGI', 'name' => 'Ezugi', 'class' => _Ezugi::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'HGCLUB', 'name' => 'HG Club', 'class' => _HGClub::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'IB', 'name' => 'Saba Sports', 'class' => IBC::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'OB', 'name' => 'Obet33', 'class' => _Obet33::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'SB', 'name' => 'SBO', 'class' => _Sportsbook::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'M8', 'name' => 'M8', 'class' => _M8::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'MD368', 'name' => 'Md368', 'class' => _MD368::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'QB838', 'name' => 'QB838', 'class' => _QB838::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'BTI', 'name' => 'BTI', 'class' => _BTI::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'CMD368', 'name' => 'CMD368', 'class' => _CMD368::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'E1SPORT', 'name' => 'E1 Sport', 'class' => _E1Sport::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'DIGMAN', 'name' => 'Dig Man', 'class' => _DigMan::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => '998BET', 'name' => '998 Bet', 'class' => _998Bet::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => '9WICKETS', 'name' => '9WicKets', 'class' => _9WicKets::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'WBET', 'name' => 'WBet', 'class' => _WBet::class, 'category' => Product::CATEGORY_SPORTS],
            // ['code' => 'TFGAMING', 'name' => 'TF Gaming', 'class' => _TFGaming::class, 'category' => Product::CATEGORY_EGAME],
            // ['code' => 'GRANDLOTTO', 'name' => 'Grand Lotto', 'class' => _GrandLotto::class, 'category' => Product::CATEGORY_LOTTERY],
            // ['code' => 'QQKENO', 'name' => 'QQ Keno', 'class' => _QQKeno::class, 'category' => Product::CATEGORY_LOTTERY],
            // ['code' => 'XPOKER', 'name' => 'X Poker', 'class' => _XPoker::class, 'category' => Product::CATEGORY_TABLE],
            // ['code' => 'POKERWIN', 'name' => 'Poker Win', 'class' => _PokerWin::class, 'category' => Product::CATEGORY_TABLE],
            // ['code' => 'HOGAMING', 'name' => 'Ho Gaming', 'class' => _HoGaming::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'YELLOWBAT', 'name' => 'Yellow Bat', 'class' => _YellowBat::class],
            // ['code' => 'RICH88', 'name' => 'Rich88', 'class' => _Rich88::class],
            // ['code' => 'CROWDPLAY', 'name' => 'CrowdPlay', 'class' => _CrowdPlay::class],
            // ['code' => 'FFF', 'name' => 'FFF', 'class' => _FFF::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'REVOLUTION', 'name' => 'Revolution', 'class' => _Revolution::class, 'category' => Product::CATEGORY_LIVE],
            // ['code' => 'SPRIBE', 'name' => 'Spribe', 'class' => _Spribe::class],
            ['code' => 'APOLLO', 'name' => 'Apollo', 'class' => _Apollo::class],
            // [
            //     'code' => '9K',
            //     'name' => '918kiss',
            //     'class' => _918kiss::class,
            //     'category' => Product::CATEGORY_APP,
            //     'web_download' => 'https://yop1.918kiss.com',
            //     'ios_download' => 'https://yop1.918kiss.com',
            //     'apk_download' => 'https://yop1.918kiss.com',
            // ],
            // [
            //     'code' => 'PS',
            //     'name' => 'Pussy888',
            //     'class' => Pussy888::class,
            //     'category' => Product::CATEGORY_APP,
            //     'web_download' => 'https://www.pussy888apk.app/',
            //     'ios_download' => 'https://www.pussy888apk.app/',
            //     'apk_download' => 'https://www.pussy888apk.app/',
            // ],
            // [
            //     'code' => 'MG',
            //     'name' => 'Mega888',
            //     'class' => Mega888::class,
            //     'category' => Product::CATEGORY_APP,
            //     'web_download' => 'http://m.mega566.com',
            //     'ios_download' => 'http://m.mega566.com',
            //     'apk_download' => 'http://m.mega566.com',
            // ],
            // [
            //     'code' => 'E8',
            //     'name' => 'Evo888',
            //     'class' => _Evo888::class,
            //     'category' => Product::CATEGORY_APP,
            //     'web_download' => 'https://d.evo366.com/',
            //     'ios_download' => 'https://d.evo366.com/',
            //     'apk_download' => 'https://d.evo366.com/',
            // ],
            // [
            //     'code' => '918KISS2',
            //     'name' => '918kiss2',
            //     'class' => _918kiss2::class,
            //     'category' => Product::CATEGORY_APP,
            //     'web_download' => 'https://m.918kiss.ws/',
            //     'ios_download' => 'https://m.918kiss.ws/',
            //     'apk_download' => 'https://m.918kiss.ws/',
            // ],
            // [
            //     'code' => 'PLAYBOY',
            //     'name' => 'PlayBoy',
            //     'class' => _Playboy::class,
            //     'category' => Product::CATEGORY_APP,
            //     'web_download' => 'https://mpb8.pb8seaanemone888.com/APP/',
            //     'ios_download' => 'https://mpb8.pb8seaanemone888.com/APP/',
            //     'apk_download' => 'https://mpb8.pb8seaanemone888.com/APP/',
            // ],
            // [
            //     'code' => 'SUNCITY',
            //     'name' => 'SunCity',
            //     'class' => _SunCity::class,
            //     'category' => Product::CATEGORY_APP,
            //     'web_download' => 'https://www.suncity2.net/download/',
            //     'ios_download' => 'https://www.suncity2.net/download/',
            //     'apk_download' => 'https://www.suncity2.net/download/',
            // ],
        ];

        foreach ($productsData as $data) {
            $product = Product::create([
                'sequence' => 0,
                'code' => $data['code'],
                'name' => $data['name'],
                'category' => $data['category'] ?? Product::CATEGORY_SLOTS,
                'class' => $data['class'],
                'image' => 'http://storewave.work/products/' . $data['code'] . '.webp',
                'status' => Product::STATUS_COMMING_SOON,
                'disclaimer' => ' '
            ]);

            CurrencyProduct::updateOrCreate(
                ['product_id' => $product->id],
                ['currency_id' => 1]
            );
        }
    }
}
