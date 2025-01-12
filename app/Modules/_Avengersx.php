<?php

namespace App\Modules;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

class _Avengersx extends Controller
{
    const Domain = "https://titan33.com/api/";
    const Operator = "IMPERIAL";
    const SecretKey = "516874f71ea2cc51b9f8cc9388fbe3124cddabc3d183db0ed753cac4c8287284";
    protected $URL;

    const ERRORS = [
        "1000" => "Invalid Operator or Secret Key",
        "1001" => "Invalid Parameter",
        "1002" => "Invalid Token",
        "1003" => "Invalid Product Code",
        "1004" => "Product Not Found",
        "1005" => "Product is suspended",
        "1006" => "Username already exists",
        "1007" => "Username not found",
        "1008" => "Account is suspended",
        "1009" => "Transaction is used",
        "1010" => "Account is expired",
        "1011" => "IP is not authorized",
        "1012" => "Product is currently under maintenance",
        "1013" => "Operator or Reseller account is suspended",
        "1014" => "API Timeout",
        "1015" => "Credit balance not enough",
        "9998" => "API currently under maintenance",
        "9999" => "API error",
    ];

    // Param
    protected $SecretKey;
    protected $Operator;
    protected $Product;
    protected $Username;
    protected $Password;
    protected $Status;
    protected $TransactionID;
    protected $Category;
    protected $ReturnUrl;
    protected $IsMobile;
    protected $Game;
    protected $Token;
    protected $Language;
    protected $BetLimit;
    protected $Page;

    public function __construct()
    {
        $this->Operator = SELF::Operator;
        $this->SecretKey = SELF::SecretKey;
        $this->Language = $this->getLocale();
    }

    public function CreateAccount()
    {
        return $this->request(__FUNCTION__);
    }

    public function GetProductUsername()
    {
        return $this->request(__FUNCTION__);
    }

    public function UpdateAccountStatus()
    {
        return $this->request(__FUNCTION__);
    }

    public function UpdateAccountPassword()
    {
        return $this->request(__FUNCTION__);
    }

    public function GetBalance()
    {
        return $this->request(__FUNCTION__);
    }

    public function Deposit()
    {
        return $this->request(__FUNCTION__);
    }

    public function Withdrawal()
    {
        return $this->request(__FUNCTION__);
    }

    public function StartGame()
    {
        return $this->request(__FUNCTION__);
    }

    public function GetBetDetail()
    {
        return $this->request(__FUNCTION__);
    }

    public function UpdateBetLimit()
    {
        return $this->request(__FUNCTION__);
    }

    public function getToken($function)
    {
        if ($function == "CreateAccount") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'Password' => $this->Password, 'Language' => $this->Language];
        }
        if ($function == "GetProductUsername") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username];
        }
        if ($function == "UpdateAccountStatus") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'Status' => $this->Status];
        }
        if ($function == "UpdateAccountPassword") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'Password' => $this->Password, 'Language' => $this->Language];
        }
        if ($function == "GetBalance") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username];
        }
        if ($function == "Deposit") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'Amount' => $this->Amount, 'TransactionID' => $this->TransactionID];
        }
        if ($function == "Withdrawal") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'Amount' => $this->Amount, 'TransactionID' => $this->TransactionID];
        }
        if ($function == "StartGame") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'Category' => $this->Category, 'IsMobile' => $this->IsMobile, 'Language' => $this->Language, 'Game' => $this->Game, 'ReturnUrl' => $this->ReturnUrl];
        }
        if ($function == "GetBetDetail") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'StartDate' => $this->StartDate, 'EndDate' => $this->EndDate, 'Page' => $this->Page];
        }
        if ($function == "UpdateBetLimit") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'BetLimit' => $this->BetLimit];
        }
    }

    public function getParam($function)
    {
        if ($function == "CreateAccount") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'Password' => $this->Password, 'Language' => $this->Language, 'Token' => $this->Token];
        }
        if ($function == "GetProductUsername") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'Token' => $this->Token];
        }
        if ($function == "UpdateAccountStatus") {
            return ['Operator' => $this->Operator, 'Token' => $this->Token, 'Product' => $this->Product, 'Username' => $this->Username, 'Status' => $this->Status];
        }
        if ($function == "UpdateAccountPassword") {
            return ['Operator' => $this->Operator, 'Token' => $this->Token, 'Product' => $this->Product, 'Username' => $this->Username, 'Password' => $this->Password, 'Language' => $this->Language];
        }
        if ($function == "GetBalance") {
            return ['Operator' => $this->Operator, 'Token' => $this->Token, 'Product' => $this->Product, 'Username' => $this->Username];
        }
        if ($function == "Deposit") {
            return ['Operator' => $this->Operator, 'Token' => $this->Token, 'Product' => $this->Product, 'Username' => $this->Username, 'Amount' => $this->Amount, 'TransactionID' => $this->TransactionID];
        }
        if ($function == "Withdrawal") {
            return ['Operator' => $this->Operator, 'Token' => $this->Token, 'Product' => $this->Product, 'Username' => $this->Username, 'Amount' => $this->Amount, 'TransactionID' => $this->TransactionID];
        }
        if ($function == "StartGame") {
            return ['Operator' => $this->Operator, 'Token' => $this->Token, 'Product' => $this->Product, 'Username' => $this->Username, 'Category' => $this->Category, 'IsMobile' => $this->IsMobile, 'Language' => $this->Language, 'Game' => $this->Game, 'ReturnUrl' => $this->ReturnUrl];
        }
        if ($function == "GetBetDetail") {
            return ['Operator' => $this->Operator, 'Token' => $this->Token, 'Product' => $this->Product, 'StartDate' => $this->StartDate, 'EndDate' => $this->EndDate, 'Page' => $this->Page];
        }
        if ($function == "UpdateBetLimit") {
            return ['Operator' => $this->Operator, 'Product' => $this->Product, 'Username' => $this->Username, 'BetLimit' => $this->BetLimit, 'Token' => $this->Token];
        }
    }

    public function Encryption($function)
    {
        return md5($this->SecretKey . $this->http_build_query($this->getToken($function)));
    }

    public function http_build_query($params)
    {
        if (array_key_exists('StartDate', $params) || array_key_exists('BetLimit', $params)) {
            $paramsJoined = array();

            foreach ($params as $param => $value) {
                $paramsJoined[] = "$param=$value";
            }

            return implode('&', $paramsJoined);
        } else {
            return http_build_query($params);
        }
    }

    public function CreateParams($params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function request($function)
    {
        $this->Token = $this->Encryption($function);
        $this->PostData = $this->getParam($function);
        $req_str = $this->http_build_query($this->PostData);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => SELF::Domain . $function,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $req_str
        ));

        $response = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return [
                'status' => false,
                'message' => "API ERROR : $err",
                'data' => [],
                'time' => $info['total_time'],
            ];
        } else {
            $response = @json_decode(preg_replace('/[[:cntrl:]]/', '', $response), true);
            if (!$response) {
                return [
                    'status' => false,
                    'message' => "Success",
                    'data' => [
                        'ErrorCode' => 9999,
                        'Message' => "API error",
                    ],
                    'time' => $info['total_time'],
                ];
            }
            return [
                'status' => true,
                'message' => "Success",
                'data' => $response,
                'time' => $info['total_time'],
            ];
        }
    }

    public static function console_getBets($date = null, $page = 1)
    {
        if ($date) {
            $start_date = Carbon::parse($date, 'Africa/Freetown')->startOfDay()->format('Y-m-d H:i:s');
            $end_date = Carbon::parse($date, 'Africa/Freetown')->endOfDay()->format('Y-m-d H:i:s');
        } else {
            $start_date = Carbon::parse(
                cache()->get(
                    'avengersx_fetch_datetime',
                    Carbon::now()->subDays(2)->setTimezone('Africa/Freetown')->subHours(1)->format('Y-m-d H:i:s')
                )
            )->format('Y-m-d H:i:s');
            $end_date = Carbon::now()->setTimezone('Africa/Freetown')->format('Y-m-d H:i:s');
        }

        $betDetails = collect();
        $controller = new _Avengersx();
        $params = ['Product' => "ALL", 'StartDate' => $start_date, 'EndDate' => $end_date, 'Page' => $page, 'Type' => 0];
        $controller->CreateParams($params);
        $GetBetDetail = $controller->GetBetDetail();

        if ($GetBetDetail['status'] === true && $GetBetDetail['data']['ErrorCode'] == 1010) {
            Log::debug("Account Expired");
            return false;
        }

        if ($GetBetDetail['status'] === false || !isset($GetBetDetail['data']['TotalPage'])) {
            if (isset($GetBetDetail['data']['ErrorCode']) && $GetBetDetail['data']['ErrorCode'] == 9999) {
                return false;
            }
            return SELF::console_getBets($date, $page);
        }

        $betDetails->push($GetBetDetail);
        if ((int)$page < (int)$GetBetDetail['data']['TotalPage']) {
            $page = $page + 1;
            sleep(rand(2, 3));
            return $betDetails->merge(SELF::console_getBets($date, $page));
        }
        cache()->put('avengersx_fetch_datetime', $end_date);

        echo $start_date . " " . $end_date . "\r\n";
        return $betDetails;
    }

    public function getLocale()
    {
        if (App::isLocale('en')) {
            return "en-US";
        }
        if (App::isLocale('ms-MY')) {
            return "ms-MY";
        }
        if (App::isLocale('zh-CN')) {
            return "zh-CN";
        }
        return "en-US";
    }

    public static function getBetItem($value)
    {
        $bet_items = [
            "0" =>  asset('assets/admin/roulette/' . 0 . '.png'),
            "1" =>  asset('assets/admin/roulette/' . 1 . '.png'),
            "2" =>  asset('assets/admin/roulette/' . 2 . '.png'),
            "3" =>  asset('assets/admin/roulette/' . 3 . '.png'),
            "4" =>  asset('assets/admin/roulette/' . 4 . '.png'),
            "5" =>  asset('assets/admin/roulette/' . 5 . '.png'),
            "6" =>  asset('assets/admin/roulette/' . 6 . '.png'),
            "7" =>  asset('assets/admin/roulette/' . 7 . '.png'),
            "8" =>  asset('assets/admin/roulette/' . 8 . '.png'),
            "9" =>  asset('assets/admin/roulette/' . 9 . '.png'),
            "10" =>  asset('assets/admin/roulette/' . 10 . '.png'),
            "11" =>  asset('assets/admin/roulette/' . 11 . '.png'),
            "12" =>  asset('assets/admin/roulette/' . 12 . '.png'),
            "13" =>  asset('assets/admin/roulette/' . 13 . '.png'),
            "14" =>  asset('assets/admin/roulette/' . 14 . '.png'),
            "15" =>  asset('assets/admin/roulette/' . 15 . '.png'),
            "16" =>  asset('assets/admin/roulette/' . 16 . '.png'),
            "17" =>  asset('assets/admin/roulette/' . 17 . '.png'),
            "18" =>  asset('assets/admin/roulette/' . 18 . '.png'),
            "19" =>  asset('assets/admin/roulette/' . 19 . '.png'),
            "20" =>  asset('assets/admin/roulette/' . 20 . '.png'),
            "21" =>  asset('assets/admin/roulette/' . 21 . '.png'),
            "22" =>  asset('assets/admin/roulette/' . 22 . '.png'),
            "23" =>  asset('assets/admin/roulette/' . 23 . '.png'),
            "24" =>  asset('assets/admin/roulette/' . 24 . '.png'),
            "25" =>  asset('assets/admin/roulette/' . 25 . '.png'),
            "26" =>  asset('assets/admin/roulette/' . 26 . '.png'),
            "27" =>  asset('assets/admin/roulette/' . 27 . '.png'),
            "28" =>  asset('assets/admin/roulette/' . 28 . '.png'),
            "29" =>  asset('assets/admin/roulette/' . 29 . '.png'),
            "30" =>  asset('assets/admin/roulette/' . 30 . '.png'),
            "31" =>  asset('assets/admin/roulette/' . 31 . '.png'),
            "32" =>  asset('assets/admin/roulette/' . 32 . '.png'),
            "33" =>  asset('assets/admin/roulette/' . 33 . '.png'),
            "34" =>  asset('assets/admin/roulette/' . 34 . '.png'),
            "35" =>  asset('assets/admin/roulette/' . 35 . '.png'),
            "36" =>  asset('assets/admin/roulette/' . 36 . '.png'),
            "101" =>  asset('assets/admin/baccarat/AS.png'),
            "102" =>  asset('assets/admin/baccarat/2S.png'),
            "103" =>  asset('assets/admin/baccarat/3S.png'),
            "104" =>  asset('assets/admin/baccarat/4S.png'),
            "105" =>  asset('assets/admin/baccarat/5S.png'),
            "106" =>  asset('assets/admin/baccarat/6S.png'),
            "107" =>  asset('assets/admin/baccarat/7S.png'),
            "108" =>  asset('assets/admin/baccarat/8S.png'),
            "109" =>  asset('assets/admin/baccarat/9S.png'),
            "110" =>  asset('assets/admin/baccarat/10S.png'),
            "111" =>  asset('assets/admin/baccarat/JS.png'),
            "112" =>  asset('assets/admin/baccarat/QS.png'),
            "113" =>  asset('assets/admin/baccarat/KS.png'),
            "201" => asset('assets/admin/baccarat/AH.png'),
            "202" => asset('assets/admin/baccarat/2H.png'),
            "203" => asset('assets/admin/baccarat/3H.png'),
            "204" => asset('assets/admin/baccarat/4H.png'),
            "205" => asset('assets/admin/baccarat/5H.png'),
            "206" => asset('assets/admin/baccarat/6H.png'),
            "207" => asset('assets/admin/baccarat/7H.png'),
            "208" => asset('assets/admin/baccarat/8H.png'),
            "209" => asset('assets/admin/baccarat/9H.png'),
            "210" => asset('assets/admin/baccarat/10H.png'),
            "211" => asset('assets/admin/baccarat/JH.png'),
            "212" => asset('assets/admin/baccarat/QH.png'),
            "213" => asset('assets/admin/baccarat/KH.png'),
            "301" =>  asset('assets/admin/baccarat/AC.png'),
            "302" =>  asset('assets/admin/baccarat/2C.png'),
            "303" =>  asset('assets/admin/baccarat/3C.png'),
            "304" =>  asset('assets/admin/baccarat/4C.png'),
            "305" =>  asset('assets/admin/baccarat/5C.png'),
            "306" =>  asset('assets/admin/baccarat/6C.png'),
            "307" =>  asset('assets/admin/baccarat/7C.png'),
            "308" =>  asset('assets/admin/baccarat/8C.png'),
            "309" =>  asset('assets/admin/baccarat/9C.png'),
            "310" =>  asset('assets/admin/baccarat/10C.png'),
            "311" =>  asset('assets/admin/baccarat/JC.png'),
            "312" =>  asset('assets/admin/baccarat/QC.png'),
            "313" =>  asset('assets/admin/baccarat/KC.png'),
            "401" =>  asset('assets/admin/baccarat/AD.png'),
            "402" =>  asset('assets/admin/baccarat/2D.png'),
            "403" =>  asset('assets/admin/baccarat/3D.png'),
            "404" =>  asset('assets/admin/baccarat/4D.png'),
            "405" =>  asset('assets/admin/baccarat/5D.png'),
            "406" =>  asset('assets/admin/baccarat/6D.png'),
            "407" =>  asset('assets/admin/baccarat/7D.png'),
            "408" =>  asset('assets/admin/baccarat/8D.png'),
            "409" =>  asset('assets/admin/baccarat/9D.png'),
            "410" =>  asset('assets/admin/baccarat/10D.png'),
            "411" =>  asset('assets/admin/baccarat/JD.png'),
            "412" =>  asset('assets/admin/baccarat/QD.png'),
            "413" =>  asset('assets/admin/baccarat/KD.png'),
            "500" =>  "Dice 0",
            "501" =>  "Dice 1",
            "502" =>  "Dice 2",
            "503" =>  "Dice 3",
            "504" =>  "Dice 4",
            "505" =>  "Dice 5",
            "506" =>  "Dice 6",
            "700" =>  "Domino 00",
            "701" =>  "Domino 01",
            "702" =>  "Domino 02",
            "703" =>  "Domino 03",
            "704" =>  "Domino 04",
            "705" =>  "Domino 05",
            "706" =>  "Domino 06",
            "711" =>  "Domino 11",
            "712" =>  "Domino 12",
            "713" =>  "Domino 13",
            "714" =>  "Domino 14",
            "715" =>  "Domino 15",
            "716" =>  "Domino 16",
            "722" =>  "Domino 22",
            "723" =>  "Domino 23",
            "724" =>  "Domino 24",
            "725" =>  "Domino 25",
            "726" =>  "Domino 26",
            "733" =>  "Domino 33",
            "734" =>  "Domino 34",
            "735" =>  "Domino 35",
            "736" =>  "Domino 36",
            "744" =>  "Domino 44",
            "745" =>  "Domino 45",
            "746" =>  "Domino 46",
            "755" =>  "Domino 55",
            "756" =>  "Domino 56",
            "766" =>  "Domino 66",
            "770" =>  "Domino 70",
            "801" =>  "Color Dice 1",
            "802" =>  "Color Dice 2",
            "803" =>  "Color Dice 3",
            "804" =>  "Color Dice 4",
            "805" =>  "Color Dice 5",
            "806" =>  "Color Dice 6",
            "807" =>  "Color Dice 7",
            "808" =>  "Color Dice 8",
            "809" =>  "Color Dice 9",
            "810" =>  "Color Dice 10",
            "811" =>  "Color Dice 11",
            "812" =>  "Color Dice 12",
            "1001" =>  "Green Card 1",
            "1002" =>  "Green Card 2",
            "1003" =>  "Green Card 3",
            "1004" =>  "Green Card 4",
            "1005" =>  "Green Card 5",
            "1006" =>  "Green Card 6",
            "1007" =>  "Green Card 7",
            "1008" =>  "Green Card 8",
            "1009" =>  "Green Card 9",
            "1010" =>  "Green Card 10",
            "1011" =>  "Green Card 11",
            "2001" =>  "Yellow Card 1",
            "2002" =>  "Yellow Card 2",
            "2003" =>  "Yellow Card 3",
            "2004" =>  "Yellow Card 4",
            "2005" =>  "Yellow Card 5",
            "2006" =>  "Yellow Card 6",
            "2007" =>  "Yellow Card 7",
            "2008" =>  "Yellow Card 8",
            "2009" =>  "Yellow Card 9",
            "2010" =>  "Yellow Card 10",
            "2011" =>  "Yellow Card 11",
            "3001" =>  "Blue Card 1",
            "3002" =>  "Blue Card 2",
            "3003" =>  "Blue Card 3",
            "3004" =>  "Blue Card 4",
            "3005" =>  "Blue Card 5",
            "3006" =>  "Blue Card 6",
            "3007" =>  "Blue Card 7",
            "3008" =>  "Blue Card 8",
            "3009" =>  "Blue Card 9",
            "3010" =>  "Blue Card 10",
            "3011" =>  "Blue Card 11",
            "4001" =>  "Purple Card 1",
            "4002" =>  "Purple Card 2",
            "4003" =>  "Purple Card 3",
            "4004" =>  "Purple Card 4",
            "4005" =>  "Purple Card 5",
            "4006" =>  "Purple Card 6",
            "4007" =>  "Purple Card 7",
            "4008" =>  "Purple Card 8",
            "4009" =>  "Purple Card 9",
            "4010" =>  "Purple Card 10",
            "4011" =>  "Purple Card 11",
            "5001" =>  "White Card 1",
            "5002" =>  "White Card 2",
            "5003" =>  "White Card 3",
            "5004" =>  "White Card 4",
            "5005" =>  "White Card 5",
            "5006" =>  "White Card 6",
            "5007" =>  "White Card 7",
            "5008" =>  "White Card 8",
            "5009" =>  "White Card 9",
            "5010" =>  "White Card 10",
            "5011" =>  "White Card 11",
        ];

        return $bet_items[$value] ?? "";
    }
}
