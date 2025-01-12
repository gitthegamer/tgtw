<?php

namespace App\Console\Commands\Data;

use App\Helpers\_3Win8;
use App\Helpers\Playtech;
use App\Helpers\_888king2;
use App\Helpers\_918kaya;
use App\Helpers\Joker;
use App\Helpers\_ACE333;
use App\Helpers\_AdvantPlay;
use App\Helpers\_Funhouse;
use App\Helpers\_Dragonsoft;
use App\Helpers\_Jili;
use App\Helpers\_Live22;
use App\Helpers\_PGS;
use App\Helpers\_PP;
use App\Helpers\_Sexybrct;
use App\Helpers\_Vpower;
use App\Helpers\_XE88;
use App\Helpers\_YesGetRich;
use App\Helpers\_AceWin;
use App\Helpers\_Apollo;
use App\Helpers\_Netent;
use App\Helpers\_RelaxGaming;
use App\Helpers\_Spinix;
use App\Http\Helpers;
use App\Models\Game;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class GetGameLists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:get_game_lists';

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
        $functions = [
            // 'getPlaytechSlots',
            // 'getJoker',
            // 'getVpower',
            // 'getPGS',
            // 'getDragonsoft',
            // 'getLive22',
            // 'getXE88',
            // 'get918Kaya',
            // 'get3Win8',
            // 'getPP',
            // 'getYesGetRich'
            // 'getPlayboy'
            // 'getNetent'
            // 'getAceWin'
            // 'getSpinix'
            'getRelaxGaming'
            // 'getApollo'
        ];

        foreach ($functions as $function) {
            try {
                $this->{$function}();
                // Helpers::sendNotification($function);
                echo $function . " done\n";
            } catch (\Exception $e) {
                $errorMessage = $function . ': ' . $e->getMessage();
                Helpers::sendNotification($errorMessage);
                echo $errorMessage . "\n";
            }
        }

        cache()->forget('gamelist_');

        return Command::SUCCESS;
    }

    public function getFunhouse()
    {
        $product = Product::where('class', _Funhouse::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);
        $response = _Funhouse::getGameList();

        foreach ($response['games'] as $game) {
            Game::updateOrCreate([
                'product_id' => $product->id,
                'code' => $game['id'],
            ], [
                'name' => $game['game_name'],
                'image' => 'https://assets.funhouse888.com/gameassets/' . $game['id'] . '/square_200.png',
                'label' => Game::LABEL_NONE,
                'meta' => $game,
            ]);
        }
    }

    public function getJoker()
    {
        $product = Product::where('class', Joker::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);
        $response = Joker::getGameList();
        $file = explode("\n", file_get_contents(app_path("Gamelist/JokerTopGameList.csv")));

        foreach ($file as $item) {
            foreach ($response['ListGames'] as $game) {
                if ($game['GameCode'] == $item) {
                    $name = [];
                    foreach ($game['Localizations'] as $locale) {
                        $name[$locale['Language']] = $locale['Name'];
                    }
                    if (strtoupper($game['GameType']) == 'SLOT') {

                        if (in_array($game['GameCode'], $file)) {
                            $label = Game::LABEL_RECOMMENDED;

                            Game::updateOrCreate([
                                'product_id' => $product->id,
                                'code' => $game['GameCode'],
                            ], [
                                'name' => $name,
                                'image' => $game['Image1'],
                                'label' => $label,
                                'meta' => $game,
                            ]);
                        }
                    }
                }
            }
        }

        foreach ($response['ListGames'] as $game) {
            $name = [];
            foreach ($game['Localizations'] as $locale) {
                $name[$locale['Language']] = $locale['Name'];
            }
            if (strtoupper($game['GameType']) == 'SLOT') {
                if (!in_array($game['GameCode'], $file)) {
                    $specialList = [];
                    if ($game['Specials'] != '') {
                        $specialList = explode(",", $game['Specials']);
                    }
                    $label = Game::LABEL_NONE;
                    if (in_array('new', $specialList)) {
                        $label = Game::LABEL_NEW;
                    }
                    if (in_array('hot', $specialList)) {
                        $label = Game::LABEL_HOT;
                    }
                    if (in_array('recommended', $specialList)) {
                        $label = Game::LABEL_RECOMMENDED;
                    }
                    Game::updateOrCreate([
                        'product_id' => $product->id,
                        'code' => $game['GameCode'],
                    ], [
                        'name' => $name,
                        'image' => $game['Image1'],
                        'label' => $label,
                        'meta' => $game,
                    ]);
                }
            }
        }
    }

    public function get888King()
    {
        $product = Product::where('class', _888king2::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);
        $response = _888king2::getGameList();

        foreach ($response['list'] as $game) {
            if ($game['game_id'] != 'bslot-005' && $game['game_id'] != 'bslot-007' && $game['game_id'] != 'bslot-016' && $game['game_id'] != 'bslot-030') {
                Game::updateOrCreate([
                    'product_id' => $product->id,
                    'code' => $game['game_id'],
                ], [
                    'name' => $game['title'],
                    'image' => $game['image_url'],
                    'label' => Game::LABEL_NONE,
                    'meta' => [
                        'url' => $game['url']
                    ],
                ]);
            }
        }
    }

    public function getPlaytechSlots()
    {
        $product = Product::where('category', Product::CATEGORY_SLOTS)->where('class', Playtech::class)->whereIn('code', ["PTS"])->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);
        $file = explode("\n", file_get_contents(app_path("Gamelist/playtech_slot_gamelist.csv")));

        foreach ($file as $game) {
            $game = explode(",", $game);
            $product1 = null;

            if (mb_convert_encoding($game[0], "ASCII") === "?SLOT" || mb_convert_encoding($game[0], "ASCII") === "SLOT" || $game[0] === "SLOT") {
                $product1 = $product;
            } else {
                dd(1, mb_convert_encoding($game[4], "ASCII"));
            }

            $gamename = "/games/playtech/" . $game[4] . ".jpg";

            Game::updateOrCreate([
                'product_id' => $product1->id,
                'code' => $game[3],
            ], [
                'name' => [
                    'en' => $game[4],
                    'cn' => $game[5],
                ],
                'image' => cdnAsset($gamename),
                'label' => Game::LABEL_NONE,
                'meta' => [],
            ]);
        }
        return Command::SUCCESS;
    }

    public function getDragonsoft()
    {
        $product_pps = Product::where('category', Product::CATEGORY_SLOTS)->where('class', _Dragonsoft::class)->whereIn('code', ["DS"])->first();
        if (!$product_pps) {
            return false;
        }
        cache()->forget('gamelist_' . $product_pps->id);
        $file = explode("\n", file_get_contents(app_path("Gamelist/dragonsoft_game_list.csv")));

        foreach ($file as $game) {
            $game = explode(",", $game);
            $product = null;
            $gameType = '';
            if (mb_convert_encoding($game[0], "ASCII") === "?捕鱼-Fishing" || mb_convert_encoding($game[0], "ASCII") === "捕鱼-Fishing" || $game[0] === "捕鱼-Fishing") {
                $product = $product_pps;
                $gameType = 'FH';
            }
            if (mb_convert_encoding($game[0], "ASCII") === "?老虎机-Slot" || mb_convert_encoding($game[0], "ASCII") === "老虎机-Slot" || $game[0] === "老虎机-Slot") {
                $product = $product_pps;
                $gameType = 'SLOT';
            }
            if (mb_convert_encoding($game[0], "ASCII") === "?街机-ARCADE" || mb_convert_encoding($game[0], "ASCII") === "街机-ARCADE" || $game[0] === "街机-ARCADE") {
                $product = $product_pps;
                $gameType = 'ARCADE';
            }

            $gamename = "/games/dragonsoft/" . $game[1] . "_en_web_512.png";

            if ($product != null) {
                Game::updateOrCreate([
                    'product_id' => $product->id,
                    'code' => $game[1],
                ], [
                    'name' => [
                        'en' => $game[3],
                        'cn' => $game[2],
                    ],
                    'image' => cdnAsset($gamename),
                    'label' => Game::LABEL_NONE,
                    'meta' => $gameType,
                ]);
            }
        }
    }

    public function get918Kaya()
    {
        $product_pps = Product::where('category', Product::CATEGORY_SLOTS)->where('class', _918kaya::class)->whereIn('code', ["918KAYA"])->first();
        if (!$product_pps) {
            return false;
        }
        cache()->forget('gamelist_' . $product_pps->id);
        $file = explode("\n", file_get_contents(app_path("Gamelist/kaya918_gamelist.csv")));

        foreach ($file as $game) {
            $game = explode(",", $game);
            $product = null;

            if (mb_convert_encoding($game[5], "ASCII") === "Slot") {
                $product = $product_pps;
            } else {
                continue;
            }

            $imageName = str_replace([' ', '.'], '', $game[1]);
            $gamename = "/games/918kaya/" . $game[0] . "_" . $imageName . ".png";

            if ($product != null) {

                Game::firstOrCreate([
                    'product_id' => $product->id,
                    'code' => $game[0],
                ], [
                    'name' => [
                        'en' => $game[1],
                        'cn' => $game[2],
                    ],
                    'image' => cdnAsset($gamename),
                    'label' => Game::LABEL_NONE,
                    'meta' => $game,
                ]);
            }
        }
        return Command::SUCCESS;
    }

    public function getVpower()
    {
        $product = Product::where('class', _Vpower::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $response = _Vpower::getGameList();

        foreach ($response['GameList'] as $game) {

            $name = [];
            $name['cn'] = $game['GameName'];
            $name['en'] = $game['GameName_EN'];

            Game::updateOrCreate([
                'product_id' => $product->id,
                'code' => $game['GameID'],
            ], [
                'name' => $name,
                'image' => $game['Image1'],
                'label' => Game::LABEL_NONE,
                'meta' => $game,
            ]);
        }
    }

    public function getPGS()
    {
        $product = Product::where('class', _PGS::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $response = _PGS::getGameList();

        foreach ($response as $game) {
            $name = [];
            $name['cn'] = null;
            $name['en'] = null;

            foreach ($game['gameNameMappings'] as $name) {
                if ($name['languageCode'] == 'zh-cn')
                    $name['cn'] = $name['text'];
                if ($name['languageCode'] == 'en-us')
                    $name['en'] = $name['text'];
            }

            Game::updateOrCreate([
                'product_id' => $product->id,
                'code' => $game['gameCode'],
            ], [
                'name' => $name,
                'image' => $game['imageUrl'],
                'label' => Game::LABEL_NONE,
                'meta' => $game,
            ]);
        }
    }

    public function getPP()
    {

        $product = Product::where('class', _PP::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $response = _PP::getGameList();
        echo json_encode($response);
        log::debug(json_encode($response));
        $gameList = $response['data']['games']['all'];
        echo json_encode($gameList);

        foreach ($gameList as $game) {
            // Only take gameTypeID = vs & cs (Slot Type)
            // if (isset($game['gameTypeID']) && ($game['gameTypeID'] == 'vs' || $game['gameTypeID'] == 'cs')) {
            if (isset($game['gameTypeID']) && ($game['gameTypeID'] == 'bj')) {
                $name = [];
                $name['cn'] = $game['gameName'];
                $name['en'] = $game['gameName'];
                $imageUrl = config('api.PP_IMAGE_LINK') . '/game_pic/square/200/' . $game['gameID'] . '.png';

                // Print the image URL
                echo 'Image URL for game ID ' . $game['gameID'] . ': ' . $imageUrl . '<br>';
                Game::updateOrCreate([
                    'product_id' => $product->id,
                    'code' => $game['gameID'],
                ], [
                    'name' => $name,
                    'image' => $imageUrl,
                    'label' => Game::LABEL_NONE,
                    'meta' => $game,
                ]);
            }
        }
        return Command::SUCCESS;
    }

    public function getYesGetRich()
    {
        $product = Product::where('class', _YesGetRich::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $gameList = _YesGetRich::getGameList();

        if (!empty($gameList)) {
            foreach ($gameList as $game) {
                if (isset($game['GameCategoryId']) && $game['GameCategoryId'] == 1) {
                    $name = [];
                    $name['cn'] = $game['CnName'];
                    $name['en'] = $game['EnName'];
                    echo json_encode($game);

                    Game::updateOrCreate([
                        'product_id' => $product->id,
                        'code' => $game['GameId'] ?? '',
                    ], [
                        'name' => $name,
                        // 'image' => $imageUrl,
                        'label' => Game::LABEL_NONE,
                        'meta' => $game,
                    ]);
                }
            }
        }
    }

    public function getApollo()
    {
        $product = Product::where('class', _Apollo::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);
        $gameList = _Apollo::getGameList();

        if (is_array($gameList) && !empty($gameList)) {
            foreach ($gameList as $game) {
                $name = [];
                $name['en'] = $game['name'];

                Game::updateOrCreate([
                    'product_id' => $product->id,
                    'code' => $game['gType'] ?? '',
                ], [
                    'name' => $name,
                    'image' => $game['image'],
                    'label' => Game::LABEL_NONE,
                    'meta' => $game,
                ]);
            }
        }
    }


    public function getRelaxGaming()
    {
        $product = Product::where('class', _RelaxGaming::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $gameList = _RelaxGaming::getGameList();

        if (!empty($gameList)) {
            foreach ($gameList as $game) {
                if (isset($game['provider']) && $game['provider'] == 'relax') {
                    $name = [];
                    $name['cn'] = $game['game_name_cn'];
                    $name['en'] = $game['game_name'];
                    echo json_encode($game);

                    Game::updateOrCreate([
                        'product_id' => $product->id,
                        'code' => $game['game_id'] ?? '',
                    ], [
                        'name' => $name,
                        'image' => $game['game_icon'],
                        'label' => Game::LABEL_NONE,
                        'meta' => $game,
                    ]);
                }
            }
        }
    }

    public function getAceWin()
    {
        $product = Product::where('class', _AceWin::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $gameList = _AceWin::getGameList();
        echo (json_encode($gameList));
        if (!empty($gameList)) {
            foreach ($gameList as $game) {
                if (isset($game['GameCategoryId']) && $game['GameCategoryId'] == 1) {
                    $name = [];
                    if (isset($game['name'])) {
                        $name['cn'] = $game['name']['zh-CN'] ?? null; // Safely access 'zh-CN' key
                        $name['en'] = $game['name']['en-US'] ?? null; // Safely access 'en-US' key
                    }
                    echo json_encode($game);

                    Game::updateOrCreate([
                        'product_id' => $product->id,
                        'code' => $game['GameId'] ?? '',
                    ], [
                        'name' => $name,
                        // 'image' => $imageUrl,
                        'label' => Game::LABEL_NONE,
                        'meta' => $game,
                    ]);
                }
            }
        }
    }
    public function getNetent()
    {
        $product = Product::where('class', _Netent::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);
        $gameList = _Netent::getGameList();
        echo json_encode($gameList);
        if (!empty($gameList['game_list']['214'])) {
            // Check if is_enabled is 0 and delete the game if it's in the database
            foreach ($gameList['game_list']['214'] as $game) {
                if ($game['is_enabled'] == 0) {
                    Game::where('product_id', $product->id)
                        ->where('code', $game['game_id'] ?? '')
                        ->delete();
                    continue;
                }
                // if the game is enabled,
                $name = [
                    // 'cn' => $game['game_name']
                    'en' => $game['game_name']
                ];
                Game::updateOrCreate([
                    'product_id' => $product->id,
                    'code' => $game['game_id'] ?? '',
                ], [
                    'name' => $name,
                    'image' => $game['game_icon_link'] ?? '',
                    'label' => Game::LABEL_NONE,
                    'meta' => $game,
                ]);
            }
        }
    }
    public function getSpinix()
    {
        $product = Product::where('class', _Spinix::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);
        $gameList = _Spinix::getGameList();
        echo json_encode($gameList);
        if (!empty($gameList['game_list']['232'])) {
            // Check if is_enabled is 0 and delete the game if it's in the database
            foreach ($gameList['game_list']['232'] as $game) {
                if ($game['is_enabled'] == 0) {
                    Game::where('product_id', $product->id)
                        ->where('code', $game['game_id'] ?? '')
                        ->delete();
                    continue;
                }
                // if the game is enabled,
                $name = [
                    // 'cn' => $game['game_name']
                    'en' => $game['game_name']
                ];
                Game::updateOrCreate([
                    'product_id' => $product->id,
                    'code' => $game['game_id'] ?? '',
                ], [
                    'name' => $name,
                    'image' => $game['game_icon_link'] ?? '',
                    'label' => Game::LABEL_NONE,
                    'meta' => $game,
                ]);
            }
        }
    }

    public function getSexybcrt()
    {
        $product_ppl = Product::where('category', Product::CATEGORY_LIVE)->where('class', _Sexybrct::class)->whereIn('code', ["SXYB"])->first();
        if (!$product_ppl) {
            return false;
        }
        cache()->forget('gamelist_' . $product_ppl->id);
        $file = explode("\n", file_get_contents(app_path("Gamelist/sexybcrt_game_list.csv")));

        foreach ($file as $game) {
            $game = explode(",", $game);
            $product = null;

            if (mb_convert_encoding($game[0], "ASCII") === "?LIVE" || mb_convert_encoding($game[0], "ASCII") === "LIVE" || $game[0] === "LIVE" || mb_convert_encoding($game[0], "ASCII") === "?LIVE" || mb_convert_encoding($game[0], "ASCII") === "LIVE" || $game[0] === "LIVE") {
                $product = $product_ppl;
            } else {
                dd(1, mb_convert_encoding($game[0], "ASCII"));
            }
            if ($product != null) {
                Game::updateOrCreate([
                    'product_id' => $product->id,
                    'code' => $game[1],
                ], [
                    'name' => [
                        'en' => $game[2],
                        'cn' => $game[3],
                    ],
                    'image' => str_replace(base_path("public"), "", base_path("public/assets/sexybcrt/" . $game[1] . "/" . "EN.png")),
                    'label' => Game::LABEL_NONE,
                    'meta' => [],
                ]);
            }
        }
    }

    public function getJili()
    {
        $product_pps = Product::where('category', Product::CATEGORY_SLOTS)->where('class', _Jili::class)->whereIn('code', ["JL"])->first();
        if (!$product_pps) {
            return false;
        }
        cache()->forget('gamelist_' . $product_pps->id);
        $file = explode("\n", file_get_contents(app_path("Gamelist/jili_game_list.csv")));

        foreach ($file as $game) {
            $game = explode(",", $game);
            $product = null;
            $gameType = '';
            if (mb_convert_encoding($game[0], "ASCII") === "?FH" || mb_convert_encoding($game[0], "ASCII") === "FH" || $game[0] === "FH") {
                $product = $product_pps;
                $gameType = 'FH';
            }
            if (mb_convert_encoding($game[0], "ASCII") === "?SLOT" || mb_convert_encoding($game[0], "ASCII") === "SLOT" || $game[0] === "SLOT") {
                $product = $product_pps;
                $gameType = 'SLOT';
            }
            if (mb_convert_encoding($game[0], "ASCII") === "?TABLE" || mb_convert_encoding($game[0], "ASCII") === "TABLE" || $game[0] === "TABLE") {
                $product = $product_pps;
                $gameType = 'TABLE';
            }

            if ($product != null) {

                Game::updateOrCreate([
                    'product_id' => $product->id,
                    'code' => $game[1],
                ], [
                    'name' => [
                        'en' => $game[2],
                        'cn' => $game[3],
                    ],
                    'image' => str_replace(base_path("public"), "", base_path("public/assets/jili/" . $game[1] . "/" . "EN.png")),
                    'label' => Game::LABEL_NONE,
                    'meta' => $gameType,
                ]);
            }
        }
    }

    public function getLive22()
    {
        $product = Product::where('class', _Live22::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $response = _Live22::getGameList();

        foreach ($response['gamelist'] as $game) {
            Game::updateOrCreate([
                'product_id' => $product->id,
                'code' => $game['g_code'],
            ], [
                'name' => $game['gameName']['gameName_enus'],
                'image' => $game['imgFileName'],
                'label' => Game::LABEL_NONE,
                'meta' => $game,
            ]);
        }
    }

    public function getAdvantPlay()
    {
        $product = Product::where('class', _AdvantPlay::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $response = _AdvantPlay::getGameList();

        foreach ($response['Games'] as $game) {
            $name = [];
            $name['cn'] = $game['IconList'][1]['Name'];
            $name['en'] = $game['IconList'][1]['Name'];

            Game::updateOrCreate([
                'product_id' => $product->id,
                'code' => $game['GameCode'],
            ], [
                'name' => $name,
                'image' => $game['IconList'][1]['Url']['492x660']['bkg'],
                'label' => Game::LABEL_NONE,
                'meta' => $game,
            ]);
        }
    }

    public function get3Win8()
    {
        $product = Product::where('class', _3Win8::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $response = _3Win8::getGameList();

        foreach ($response['list'] as $game) {
            $name = [];
            $name['en'] = $game['game_display_name']['english'];
            $name['cn'] = $game['game_display_name']['chinese'];

            Game::updateOrCreate([
                'product_id' => $product->id,
                'code' => $game['game_code'],
            ], [
                'name' => $name,
                'image' => $game['game_image_url'],
                'label' => Game::LABEL_NONE,
                'meta' => $game,
            ]);
        }
    }

    public function getACE33()
    {
        $product = Product::where('class', _ACE333::class)->first();
        if (!$product) {
            return false;
        }
        cache()->forget('gamelist_' . $product->id);

        $response = _ACE333::getGameList();

        foreach ($response['gamelist'] as $game) {
            Game::updateOrCreate([
                'product_id' => $product->id,
                'code' => $game['gameId'],
            ], [
                'name' => $game['gameName'],
                'image' => $game['imgFileName'],
                'label' => Game::LABEL_NONE,
                'meta' => $game,
            ]);
        }
    }

    public function getPlayBoy()
    {
        // using APP

        // $product = Product::where('class', _Playboy::class)->first();
        // if (!$product) {
        //     return false;
        // }

        // $response = _Playboy::getGameList();

        // foreach ($response['gamelist'] as $game) {
        //     Game::updateOrCreate([
        //         'product_id' => $product->id,
        //         'code' => $game['g_code'],
        //     ], [
        //         'name' => $game['gameName']['gameName_enus'],
        //         'image' => $game['imgFileName'],
        //         'label' => Game::LABEL_NONE,
        //         'meta' => $game,
        //     ]);
        // }
    }

    public function getXE88()
    {
        $product_pps = Product::where('category', Product::CATEGORY_SLOTS)->where('class', _XE88::class)->whereIn('code', ["XE88"])->first();
        if (!$product_pps) {
            return false;
        }
        cache()->forget('gamelist_' . $product_pps->id);

        $file = explode("\n", file_get_contents(app_path("Gamelist/xe88_gamelist.csv")));

        foreach ($file as $game) {

            $game = explode(",", $game);
            $product = null;
            $gameType = '';

            if (mb_convert_encoding($game[3], "ASCII") === "Slots") {
                $product = $product_pps;
                $gameType = 'SLOT';
            }
            if (mb_convert_encoding($game[3], "ASCII") === "Arcade") {
                $product = $product_pps;
                $gameType = 'ARCADE';
            }
            if (mb_convert_encoding($game[3], "ASCII") === "Table") {
                $product = $product_pps;
                $gameType = 'TABLE';
            }

            $gamename = "/games/XE88/" . $game[0] . ".png";

            if ($product != null) {
                Game::firstOrCreate([
                    'product_id' => $product->id,
                    'code' => $game[0],
                ], [
                    'name' => [
                        'en' => $game[1],
                        'cn' => $game[2],
                    ],
                    'image' => cdnAsset($gamename),
                    'label' => Game::LABEL_NONE,
                    'meta' => $gameType,
                ]);
            }
        }
    }

    function checkRemoteFile($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, str_replace(" ", "%20", $url));
        // don't download content
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result !== FALSE) {
            return $code == 200;
        }

        return false;
    }
}
