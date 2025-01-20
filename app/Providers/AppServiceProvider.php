<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $keys = [
            //EMPIRE88
            "APP_NAME" => "GAMER",
            "APP_FRONT" => "REACT",
            "SERVER_URL" => "https://game.thegamer.dev",
            "MONEY_URL" => "https://game.thegamer.dev",
            "S3_URL" => "",
            "S3_REGION" => "TOKYO",
            "CDN_URL" => "",


            //STAGING
            "PGS_MERCHANT_ID_LIVE" => "", //only for testing, empire no have PGS
            "PGS_MERCHANT_KEY_LIVE" => "",
            "PGS_LINK_LIVE" => "https://vendor-api.888star.xyz/v1/",
            "BG_ENV" => "staging",
            "BG_SN" => "am00",
            "BG_SECRET_KEY" => "8153503006031672EF300005E5EF6AEF",
            "BG_AGENT_ID" => "stargamestaging1",
            "BG_AGENT_PW" => "stargamestaging11",
            "BG_BET_LIMIT" => "Y2",
            "BG_LINK" => "http://am.bgvip55.com/open-cloud/api/",
            "LK365_SECRET_KEY" => "",
            "LK365_SIGNATURE_KEY" => "",
            "LK365_LINK" => "http://api365.slotgame4ubridge.com/",
            "LK365_GAME_LINK" => "https://game.lucky365.cc/",
            "ADVANT_PLAY_SEQ" => "", //this is ace88 staging
            "ADVANT_PLAY_SECRET_KEY" => "",
            "ADVANT_PLAY_LINK" => "https://sitapi.pypc.net/api/richdaddy2_t519/",
            "ADVANT_PLAY_GAME_LINK" => "https://sitapi.pypc.net/web/richdaddy2_t519/lobby/LaunchLobby",
            "SV388_PREFIX" => "254",
            "SV388_CURRENCY" => "MYR",
            "SV388_CERT" => "TFknE5kP7RcXtS148mc",
            "SV388_USER_ID" => "zuesstaging",
            "SV388_LINK" => "https://tttint.apihub55.com",
            "SV388_REPORT_LINK" => "https://tttint.apihub55.com",
            "SEXY_LINK" => "https://tttint.onlinegames22.com/", //need change link in production
            "SEXY_REPORT_LINK" => "https://tttint.onlinegames22.com/",
            "SEXY_CERT" => "",
            "SEXY_USER_ID" => "",
            "AWC_CERT" => "aNdAAvIsn7YtIgFLCaF",
            "AWC_USER_ID" => "empire88staging",
            "AWC_CURRENCY" => "MYR",
            "AWC_LINK" => "https://tttint.apihub55.com/",
            "AWC_REPORT_LINK" => "https://tttint.apihub55.com/",
            "NEXTSPIN_TOKEN" => "",
            "NEXTSPIN_MERCHANTCODE" => "",
            "NEXTSPIN_LINK" => "https://merchantapi.bw-ns-api.com/api/",
            "NEXTSPIN_GAME_LINK" => "https://lobby.luckypig188.com/",
            "FUNKYGAMES_TOKEN" => "",
            "FUNKYGAMES_AGENT" => "",
            "FUNKYGAMES_LINK" => "http://trial-gp-api.funkytest.com/",
            "FUNKYGAMES_REPORT_LINK" => "http://trial-gp-api-report.funkytest.com/",
            "FUNKYGAMES_AUTH" => "",
            "QB838_KEY" => "",
            "QB838_PREFIX" => "",
            "QB838_APIPASS" => "",
            "QB838_AGENT" => "",
            "QB838_LINK" => "https://accessapi.onetball.com/",
            "WM_VENDOR_ID" => "",
            "WM_KEY" => "",
            "WM_LINK" => "http://api.a45.me/api/public/Gateway.php?",
            "CMD368_PARTNERCODE" => "",
            "CMD368_PARTNERKEY" => "",
            "CMD368_API_LINK" => "http://api.1win888.net/",
            "CMD368_LINK" => "https://zd283.1win888.net/",
            "CMD368_MOBILE_LINK" => "https://zd283smart.1win888.net/",



            // NO SURE ENV
            "TFGAMING_PARTNERCODE" => "7525",
            "TFGAMING_PUBLICKEY" => "a2a90f9476b74710935f628541f47542",
            "TFGAMING_PRIVATEKEY" => "00024069daa746a9400eb0168468bc8ba2d821ef1356b176199a576a9f7b691a46bbd4c66ea591c5299b0b5d40857bf8e8abb07b426434f426132c087191913868bb93916c946a758830bb4043eb121189f634fc2cf2a21fd370355d6f3f5150b2216109225e041686b32e65b43dd728fe36b1e95d182a56db3cfda547fa7dd5e4be",
            "TFGAMING_API_LINK" => "https://spi-test.r4espt.com/api/v2/",
            "WCASINO_SECRET" => "",
            "WCASINO_AGENT" => "",
            "WCASINO_CURRENCY" => "",
            "WCASINO_APPID" => "",
            "WCASINO_LINK" => "https://api.98kbus.top/api/",
            "ASTAR_KEY" => "1o6HblwE1k4iOnDE",
            "ASTAR_CHANNEL" => "EMP",
            "ASTAR_LINK" => "https://api.astar66.com/",
            "ASTAR_AUTHORIZATION" => "eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJFTVAiLCJBUElLRVkiOiIxbzZIYmx3RTFrNGlPbkRFIn0.CoH1N75vw0C2seAhm6rKgL6F8-tfnFZu5WW6mp-3Si2Jvd99Sux7dghfxBQ-6SAKWXtz6SeWZj0CuaGMwju74g",
            "DIGMAN_CODE" => "MB0801FY",
            "DIGMAN_KEY" => "F8B3CBCED4A843F48F241D2DA5E27C5E",
            "DIGMAN_LINK" => "https://api8745.cfb2.net/",
            "DIGMAN_GAME_LINK" => "https://kss.cfb2.net/api/",
            "DIGMAN_MOBILE_LINK" => "https://digmaantest.cm3645.com/api/",
            "PLAYBOY_MERCHANT" => "EMpire888",
            "PLAYBOY_AGENT" => "EMpire88801",
            "PLAYBOY_LINK" => "https://pbapi.goduckapi888.com/",
            "PLAYBOY_KEY" => "K12vu#1s~IghXvtAEg%N",
            "PLAYBOY_PREFIX" => "EMP8_",
            "PP_LINK" => "https://api.prerelease-env.biz/IntegrationService/v3/http/CasinoGameAPI",
            "PP_LOBBY_LINK" => "https://api.prerelease-env.biz/IntegrationService/v3/http",
            "PP_BETLOGS_LINK" => "https://api.prerelease-env.biz/IntegrationService/v3",
            "PP_IMAGE_LINK" => "https://common-static.ppgames.net",
            "PP_SECRET_KEY" => "80645315782043F6",
            "PP_SECURELOGIN" => "fkk2_richdaddy",
            "YGR_AGENT_ID" => "ZUES",
            "YGR_AGENT_KEY" => "Jitt3Y",
            "YGR_LINK" => "https://igmix-api.yahutech.com",
            "ACEWIN_AGENT_ID" => "zues_myr",
            "ACEWIN_AGENT_KEY" => "060dff0adce6ecb4654d84af0b61cb3b7f120a78",
            "ACEWIN_LINK" => "https://macross-platform-ag-stg.acewinplusfafafa.com",
            "MEGAH5_LINK" => "https://smapi.xystem138.com/api/opgateway/v1/op/", //Production
            // "MEGAH5_LINK" => " https://smakermicsvc.back138.com/api/opgateway/v1/op/", //Staging
            "MEGAH5_KEY" => "APVWCMTJU0KHUWR9",
            "MEGAH5_AGENT" => "mg5Kz00024MYR",
            "HOGAMING_API_LINK" => "https://zeus.hogeocdn.com/cgibin/EGameIntegration",
            "HOGAMING_LOBBY_LINK" => "https://zeus.hogeocdn.com/login/visitor/checkLoginGI.jsp?",
            "HOGAMING_WEB_API_LINK" => "https://v4webapi.hointeractive.com/",
            "HOGAMING_USERNAME" => "zeuslive_live",
            "HOGAMING_PASSWORD" => "9LUen03F!73zeKp4JG",
            "HOGAMING_CASINOID" => "zues29july24live",
            // "YELLOWBAT_LINK" => "https://api.ybdevh5.net/apiRequest.do", // UAT
            "YELLOWBAT_LINK" => "https://api.ybdigit.net/apiRequest.do", // PROD
            "YELLOWBAT_KEY" => "ceec73c9e7cf2a5b",
            "YELLOWBAT_AGENT" => "zues",
            "YELLOWBAT_IV" => "054416f19252ecff",
            "YELLOWBAT_DC" => "TITANT",
            "WHITECLIFF_TOKEN" => "fd3972ac2dd877f5b5ae9b345d1f5143",
            "WHITECLIFF_AGENT" => "MXM3501",
            "WHITECLIFF_LINK" => "https://uat.transfer.ps9games.com",
            "FFF_LINK" => "https://www.fhdapi-fb29j3.com/",
            "FFF_AES_256_CBC_KEY" => "RccTYXkTcbnTdLSh75U/ToHtV/Xzol3C",
            "FFF_MD5_KEY" => "tDJTgDy6VBwhCetYf0R8DdY0ruGBtA+f",
            "REV_APIKEY" => "89c74a0d-c851-4570-8a44-de7f73a83c82",
            "REV_LINK" => "https://test_api.r-gaming.com/api/",
            "RELAX_GAMING_LINK" => "https://gaming.stagedc.net/",
            "RELAX_GAMING_API_KEY" => "890E8ABAF38F43B38E992C705FC84325", //Zues
            "RELAX_GAMING_GET_LINK" => "https://ticket.stagedc.net",
            "RELAX_GAMING_BRAND_ID" => "T011060", // Zues
        ];


        config()->set('api', $keys);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
