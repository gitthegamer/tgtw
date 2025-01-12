<?php

namespace App\Console\Commands\GetBets;

use App\Helpers\_Dragonsoft;
use App\Models\Bet;
use App\Models\BetLog;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\OperatorProduct;
use App\Models\Product;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class _DragonsoftBets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get_bets:_DragonsoftBets {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->copy();
        $timezone = new DateTimeZone('UTC');
        $date = $date->setTimezone($timezone);
        $endDate = $date->format("Y-m-d\TH:i:s\Z");
        $startDate = $date->subHour()->format("Y-m-d\TH:i:s\Z");
        $betTickets = _Dragonsoft::getBets($startDate, $endDate, 0);
        $this->process($betTickets);
        return 0;
    }

    public function process($betTickets)
    {
        if ($betTickets && count($betTickets) > 0) {
            $upserts = [];
            
            foreach ($betTickets as $betTicket) {
                if ($betTicket['payout_amount'] - $betTicket['bet_amount'] > 0) {
                    $payout_status = "WIN";
                } elseif ($betTicket['payout_amount'] - $betTicket['bet_amount'] < 0) {
                    $payout_status = "LOSE";
                } else {
                    $payout_status = "DRAW";
                }

                switch($betTicket['status']){
                    case 1:
                        $bet_status = "SETTLED";
                        break;
                    case 2:
                        $bet_status = "REFUND";
                        break;
                    case 3:
                        $bet_status = "BET REFUSED";
                        break;
                    case 4:
                        $bet_status = "BET RECORD VOIDED";
                        break;
                    case 5:
                        $bet_status = "BET CANCELLED";
                        break;
                    default:
                        $bet_status = "UNKNOWN";
                        break;
                }
                
                $gameName = '';
                if($betTicket['game_id'] == 1001){
                    $gameName = '海霸王,Ocean Lord';
                }
                if($betTicket['game_id'] == 1002){
                    $gameName = '吃我一炮,Let\'s Shoot';
                }
                if($betTicket['game_id'] == 1003){
                    $gameName = '三仙捕鱼,3 Gods Fishing';
                }

                if($betTicket['game_id'] == 1004){
                    $gameName = '猎龙高手,Dino Hunter';
                }
                if($betTicket['game_id'] == 1006){
                    $gameName = '一槌爆富,Big Hammer';
                }
                if($betTicket['game_id'] == 1007){
                    $gameName = '植物大战恐龙,Plants vs. Dinos';
                }
                if($betTicket['game_id'] == 1008){
                    $gameName = '西游降魔,Demon Conquered';
                }
                if($betTicket['game_id'] == 1009){
                    $gameName = '三仙劈鱼,Gods Slash Fish';
                }
                if($betTicket['game_id'] == 1010){
                    $gameName = '宾果捕鱼,Bingo Fishing';
                }
                if($betTicket['game_id'] == 1011){
                    $gameName = '招财猫钓鱼,Cat Fishing';
                }
                if($betTicket['game_id'] == 3001){
                    $gameName = '钻石大亨,Diamond Mogul';
                }
                if($betTicket['game_id'] == 3002){
                    $gameName = '鱼跃龙门,Over Dragon\'s Gate';
                }
                if($betTicket['game_id'] == 3003){
                    $gameName = '发起来,Get Money';
                }
                if($betTicket['game_id'] == 3004){
                    $gameName = '狮霸天下,Great Lion';
                }
                if($betTicket['game_id'] == 3005){
                    $gameName = '大秘宝,Ultra Treasure';
                }
                if($betTicket['game_id'] == 3006){
                    $gameName = '埃及神谕,Egypt Oracle';
                }
                if($betTicket['game_id'] == 3007){
                    $gameName = '财神到,Caishen Coming';
                }
                if($betTicket['game_id'] == 3008){
                    $gameName = '大糖盛世,Candy Dynasty';
                }
                if($betTicket['game_id'] == 3009){
                    $gameName = '发财狮,Rich Lion';
                }
                if($betTicket['game_id'] == 3010){
                    $gameName = '大圣猴哥,Monkey King';
                }
                if($betTicket['game_id'] == 3011){
                    $gameName = '抢金库,Bust Treasury';
                }
                if($betTicket['game_id'] == 3012){
                    $gameName = '五圣兽,5 God Beast';
                }
                if($betTicket['game_id'] == 3013){
                    $gameName = '嗨起来,Get High';
                }
                if($betTicket['game_id'] == 3014){
                    $gameName = '阿拉伯,Arab';
                }
                if($betTicket['game_id'] == 3015){
                    $gameName = '饿狼传说,Wolf Legend';
                }
                if($betTicket['game_id'] == 3016){
                    $gameName = '水果晶钻,Crystal Fruits';
                }
                if($betTicket['game_id'] == 3017){
                    $gameName = '高尔夫,Golf';
                }
                if($betTicket['game_id'] == 3018){
                    $gameName = '虎霸王,Tiger Lord';
                }
                if($betTicket['game_id'] == 3019){
                    $gameName = '宙斯神,Zeus';
                }
                if($betTicket['game_id'] == 3020){
                    $gameName = '德古拉,Dracula';
                }
                if($betTicket['game_id'] == 3021){
                    $gameName = '海盗王,Pirate King';
                }

                if($betTicket['game_id'] == 3022){
                    $gameName = '爱丽丝,Alice';
                }
                if($betTicket['game_id'] == 3023){
                    $gameName = '石器原始人,Stone Hominid';
                }
                if($betTicket['game_id'] == 3024){
                    $gameName = '雷霸龙,T-Rex';
                }
                if($betTicket['game_id'] == 3025){
                    $gameName = '马戏之王,Greatest Circus';
                }
                if($betTicket['game_id'] == 3026){
                    $gameName = '点石成金,Midas Touch';
                }
                if($betTicket['game_id'] == 3027){
                    $gameName = '马雅王,Maya King';
                }
                if($betTicket['game_id'] == 3028){
                    $gameName = '水果Bar,Fruits Bar';
                }
                if($betTicket['game_id'] == 3029){
                    $gameName = '发财龙,Rich Dragon';
                }
                if($betTicket['game_id'] == 3030){
                    $gameName = '金猴爷,JIN HOU YE';
                }
                if($betTicket['game_id'] == 3031){
                    $gameName = '福神到,Fushen Coming';
                }
                if($betTicket['game_id'] == 3032){
                    $gameName = '熊猫侠,Pandaria';
                }
                if($betTicket['game_id'] == 3033){
                    $gameName = '马上发,Rich Now';
                }
                if($betTicket['game_id'] == 3034){
                    $gameName = '777,777';
                }
                if($betTicket['game_id'] == 3035){
                    $gameName = '三倍猴哥,Triple Monkey';
                }
                if($betTicket['game_id'] == 3036){
                    $gameName = '赵云无双,ZHAO YUN';
                }
                if($betTicket['game_id'] == 3037){
                    $gameName = '火凤凰,Phoenix';
                }
                if($betTicket['game_id'] == 3038){
                    $gameName = '吕姬无双,LU LING QI';
                }
                if($betTicket['game_id'] == 3039){
                    $gameName = '宝你发,Booming Gems';
                }
                if($betTicket['game_id'] == 3040){
                    $gameName = '很多妹子,Many Beauties';
                }
                if($betTicket['game_id'] == 3041){
                    $gameName = '更多妹子,More Beauties';
                }
                if($betTicket['game_id'] == 3042){
                    $gameName = '旺财神,Doggy Wealth';
                }
                if($betTicket['game_id'] == 3043){
                    $gameName = '给猫金币,Coin Cat';
                }
                if($betTicket['game_id'] == 3044){
                    $gameName = '天外飞仙,Immortal Heroes';
                }
                if($betTicket['game_id'] == 3045){
                    $gameName = '送钱鼠,Coin Rat';
                }
                if($betTicket['game_id'] == 3046){
                    $gameName = '小厨娘,Chef Lady';
                }
                if($betTicket['game_id'] == 3047){
                    $gameName = '宝矿利,Ore Power';
                }
                if($betTicket['game_id'] == 3048){
                    $gameName = '冰果霸,Icy Bar';
                }
                if($betTicket['game_id'] == 3049){
                    $gameName = '黄金眼,Golden Eye';
                }
                if($betTicket['game_id'] == 3050){
                    $gameName = '轰炸鸡,Chicken Dinner';
                }
                if($betTicket['game_id'] == 3051){
                    $gameName = '蔬香人家,Farm Family';
                }
                if($betTicket['game_id'] == 3052){
                    $gameName = '罗马帝国,Roman';
                }
                if($betTicket['game_id'] == 3053){
                    $gameName = '大钻石,Big Diamond';
                }
                if($betTicket['game_id'] == 3054){
                    $gameName = '拳力制霸,Muay Thai';
                }
                if($betTicket['game_id'] == 3055){
                    $gameName = '埃及王朝,Egypt Dynasty';
                }
                if($betTicket['game_id'] == 3056){
                    $gameName = '魔龙秘宝,Dragon\'s Treasure';
                }
                if($betTicket['game_id'] == 3057){
                    $gameName = '你会胡,You Will Win';
                }
                if($betTicket['game_id'] == 3058){
                    $gameName = '发红包,Give You Money';
                }
                if($betTicket['game_id'] == 3059){
                    $gameName = '犇牛宝,Rich Ox';
                }
                if($betTicket['game_id'] == 3060){
                    $gameName = '七色宝钻,Diamond 7';
                }
                if($betTicket['game_id'] == 3061){
                    $gameName = '给猫红包,Bonus Cat';
                }
                if($betTicket['game_id'] == 3062){
                    $gameName = '幕府将军,Bakufu Shogun';
                }
                if($betTicket['game_id'] == 3063){
                    $gameName = '印度神虎,Bengal Tiger';
                }
                if($betTicket['game_id'] == 3064){
                    $gameName = '龙来发,Dragonburst';
                }
                if($betTicket['game_id'] == 3065){
                    $gameName = '犎牛暴起,Buffalo Burst';
                }
                if($betTicket['game_id'] == 3067){
                    $gameName = '钱滚钱,Roll in Money';
                }
                if($betTicket['game_id'] == 3068){
                    $gameName = '世界波,Worldie';
                }
                if($betTicket['game_id'] == 3069){
                    $gameName = '大警长,Grand Sheriff';
                }
                if($betTicket['game_id'] == 3070){
                    $gameName = '霸王別姬,Overlord & Concubine';
                }
                if($betTicket['game_id'] == 4001){
                    $gameName = '梯子游戏,Ladder Game';
                }
                if($betTicket['game_id'] == 4002){
                    $gameName = '射龙门,Acey Deucey';
                }
                if($betTicket['game_id'] == 4003){
                    $gameName = '淘金蛋,Golden Egg';
                }
                if($betTicket['game_id'] == 4004){
                    $gameName = '金蟾祖玛,Golden Zuma';
                }
                if($betTicket['game_id'] == 4005){
                    $gameName = '跳跳跳,Jump & Jump';
                }
                if($betTicket['game_id'] == 4006){
                    $gameName = '凤飞飞,Flying Phoenix';
                }
                if($betTicket['game_id'] == 4007){
                    $gameName = '疯狂龙珠,Crazy Orb';
                }
                if($betTicket['game_id'] == 4009){
                    $gameName = '冲装啦,Let\'s Enhance';
                }
                if($betTicket['game_id'] == 4013){
                    $gameName = '跳龙门,Dragon or Crash';
                }

                $gameCategory = Product::CATEGORY_SLOTS;

                
                $accountDate = new DateTime($betTicket['finish_at'], new DateTimeZone('UTC'));
                $roundAt =  new DateTime($betTicket['bet_at'], new DateTimeZone('UTC'));
                $accountDate->setTimezone(new DateTimeZone('GMT+8'));
                $roundAt->setTimezone(new DateTimeZone('GMT+8'));

                $betDetail = [
                    'bet_id' => 'DS_'.$betTicket['id'],
                    'product' => "DS",
                    'game' => $gameName,
                    'category' => $gameCategory,
                    'username' => $betTicket['member'],
                    'stake' => $betTicket['bet_amount'],
                    'valid_stake' => $betTicket['bet_amount'],
                    'payout' => $betTicket['payout_amount'], 
                    'winlose' => $betTicket['payout_amount'] - $betTicket['bet_amount'], 
                    'jackpot_win' => 0,
                    'progressive_share' => 0,
                    'payout_status' => $payout_status,
                    'bet_status' => $bet_status,
                    'account_date' => $accountDate->format('Y-m-d H:i:s'),
                    'round_at' => $roundAt->format('Y-m-d H:i:s'),
                    'round_date' => $roundAt->format('Y-m-d'),
                    'modified_at' => now(),
                    'modified_date' => now()->format('Y-m-d'),
                    'bet_detail' => json_encode($betTicket),
                ];

                $upserts[] = $betDetail;
            }

            BetLog::upsertByChunk($upserts);
        }
    }
}
