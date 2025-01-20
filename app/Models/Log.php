<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log as FacadesLog;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\DataTables;

class Log extends Model
{
    const STATUS_PENDING = 0, STATUS_SUCCESS = 1, STATUS_FAILED = 2, STATUS_ERROR = 3;
    const STATUS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_SUCCESS => 'Success',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_ERROR => 'Error',
    ];

    const CHANNEL_3Win8 = 0, CHANNEL_28Win = 1, CHANNEL_888King = 2, CHANNEL_918Kaya = 3, CHANNEL_918Kiss = 4, CHANNEL_918Kiss2 = 5,
        CHANNEL_Ace333 = 6, CHANNEL_AG = 7, CHANNEL_AWC = 8, CHANNEL_BG = 9, CHANNEL_DS = 10, CHANNEL_DG = 11, CHANNEL_Evo888 = 12,
        CHANNEL_Evolution = 13, CHANNEL_FH = 14, CHANNEL_GW99 = 15, CHANNEL_IBC = 16, CHANNEL_JOKER = 17, CHANNEL_King855 = 18,
        CHANNEL_LK = 19, CHANNEL_L22 = 20, CHANNEL_L365 = 21, CHANNEL_M8 = 22, CHANNEL_MG = 23, CHANNEL_OBET33 = 24, CHANNEL_PGS = 25,
        CHANNEL_PLAYBOY = 26, CHANNEL_PLAYTECH = 27, CHANNEL_PS = 28, CHANNEL_SEXY = 29, CHANNEL_SBO = 30, CHANNEL_SUNCITY = 31, CHANNEL_VP = 32,
        CHANNEL_XE88 = 33, CHANNEL_AP = 34, CHANNEL_AllBet = 35, CHANNEL_JILI = 36, CHANNEL_PP = 37, CHANNEL_SAGAMING = 38,
        CHANNEL_SPADEGAMING = 39, CHANNEL_JDBGAMING = 40, CHANNEL_FACHAI = 41, CHANNEL_NEXTSPIN = 42, CHANNEL_FUNKYGAMES = 43, CHANNEL_QB838 = 44, CHANNEL_BTI = 45,
        CHANNEL_WBET = 46, CHANNEL_WM = 47, CHANNEL_CMD368 = 48, CHANNEL_RCB988 = 49, CHANNEL_TFGAMING = 50, CHANNEL_WCASINO = 51, CHANNEL_ASTAR = 52,
        CHANNEL_DIGMAN = 53, CHANNEL_SV388 = 54, CHANNEL_YGR = 55, CHANNEL_MegaH5 = 56, CHANNEL_MK = 57, CHANNEL_HOGAMING = 58, CHANNEL_YELLOWBAT = 59, CHANNEL_RICH88 = 60,
        CHANNEL_EZUGI = 61, CHANNEL_HOTROAD = 62, CHANNEL_NETENT = 63, CHANNEL_SPINIX = 64, CHANNEL_ACEWIN = 65, CHANNEL_CROWDPLAY = 66, CHANNEL_FFF = 67,
        CHANNEL_REVOLUTION = 68, CHANNEL_RELAXGAMING = 69, CHANNEL_ALLINONE = 70, 

        CHANNEL_PROGRESS_DEPOSIT = 1000, CHANNEL_PROGRESS_WITHDRAW = 1001, CHANNEL_ADJUSTMENT = 1002, CHANNEL_REBATE = 1003, CHANNEL_COMMISSION = 1004, CHANNEL_BONUS = 1005, CHANNEL_MALL = 1006,
        CHANNEL_POWERPG_DEPOSIT = 1007, CHANNEL_WITHDRAW = 1008, CHANNEL_POWERPG_CALLBACK = 1009, CHANNEL_DGPIN_DEPOSIT = 1010, CHANNEL_DGPIN_CALLBACK = 1011, CHANNEL_PEPAY_DEPOSIT = 1012, CHANNEL_PEPAY_CALLBACK = 1013, CHANNEL_DGPAY_USDT_DEPOSIT = 1014, CHANNEL_DGPAY_USDT_CALLBACK = 1015, CHANNEL_FD_INTEREST = 1016,
        CHANNEL_MAINWALLET_TRANSFER_IN = 1017, CHANNEL_MAINWALLET_TRANSFER_OUT = 1018, CHANNEL_LOCKWALLET_TRANSFER_IN = 1019, CHANNEL_LOCKWALLET_TRANSFER_OUT = 1020, CHANNEL_REFERRAL_BONUS = 1021,
        CHANNEL_OK2PAY_DEPOSIT = 1022, CHANNEL_OK2PAY_CALLBACK = 1023,

        CHANNEL_AGENT_PROGRESS_DEPOSIT = 2000, CHANNEL_AGENT_PROGRESS_WITHDRAW = 2001, CHANNEL_AGENT_ADJUSTMENT = 2002, CHANNEL_AGENT_REBATE = 2003, CHANNEL_AGENT_COMMISSION = 2004, CHANNEL_AGENT_BONUS = 2005, CHANNEL_AGENT_WITHDRAW = 2006, CHANNEL_AGENT_FD_INTEREST = 2007,
        CHANNEL_AGENT_MAINWALLET_TRANSFER_IN = 2008, CHANNEL_AGENT_MAINWALLET_TRANSFER_OUT = 2009, CHANNEL_AGENT_PROFIT_SHARE = 2010,

        CHANNEL_MESSAGE = 9000, CHANNEL_OTHERS = 9999,

        CHANNELS = [
            self::CHANNEL_3Win8 => '3Win8',
            self::CHANNEL_28Win => '28Win',
            self::CHANNEL_888King => '888King',
            self::CHANNEL_918Kaya => '918Kaya',
            self::CHANNEL_918Kiss => '918Kiss',
            self::CHANNEL_918Kiss2 => '918Kiss2',
            self::CHANNEL_Ace333 => 'Ace333',
            self::CHANNEL_AG => 'Asia Gaming',
            self::CHANNEL_AWC => 'AWC',
            self::CHANNEL_BG => 'Big Gaming',
            self::CHANNEL_DS => 'Dragonsoft',
            self::CHANNEL_DG => 'DG',
            self::CHANNEL_Evo888 => 'Evo888',
            self::CHANNEL_Evolution => 'Evolution',
            self::CHANNEL_FH => 'FH',
            self::CHANNEL_GW99 => 'GW99',
            self::CHANNEL_IBC => 'IBC',
            self::CHANNEL_JOKER => 'JOKER',
            self::CHANNEL_King855 => 'King855',
            self::CHANNEL_LK => 'LK',
            self::CHANNEL_L22 => 'L22',
            self::CHANNEL_L365 => 'L365',
            self::CHANNEL_M8 => 'M8',
            self::CHANNEL_MG => 'MG',
            self::CHANNEL_OBET33 => 'OBET33',
            self::CHANNEL_PGS => 'PGS',
            self::CHANNEL_PLAYBOY => 'PLAYBOY',
            self::CHANNEL_PLAYTECH => 'PLAYTECH',
            self::CHANNEL_PS => 'PS',
            self::CHANNEL_SEXY => 'SEXY',
            self::CHANNEL_SBO => 'SBO',
            self::CHANNEL_SUNCITY => 'SUNCITY',
            self::CHANNEL_VP => 'VP',
            self::CHANNEL_XE88 => 'XE88',
            self::CHANNEL_AP => 'Advant Play',
            self::CHANNEL_AllBet => 'AllBet',
            self::CHANNEL_JILI => 'JILI',
            self::CHANNEL_PP => 'Pragmatic Play',
            self::CHANNEL_SAGAMING => 'SA Gaming',
            self::CHANNEL_SPADEGAMING => 'Spade Gaming',
            self::CHANNEL_JDBGAMING => 'JDB Gaming',
            self::CHANNEL_FACHAI => 'FaChai',
            self::CHANNEL_NEXTSPIN => 'NextSpin',
            self::CHANNEL_FUNKYGAMES => 'Funky Games',
            self::CHANNEL_QB838 => 'QB838',
            self::CHANNEL_CMD368 => 'CMD368',
            self::CHANNEL_WM => 'WM CASINO',
            self::CHANNEL_TFGAMING => 'TF GAMING',
            self::CHANNEL_WCASINO => 'W Casino',
            self::CHANNEL_ASTAR => 'Astar Casino',
            self::CHANNEL_DIGMAN => 'Digman',
            self::CHANNEL_POWERPG_DEPOSIT => 'PowerPG Deposit',
            self::CHANNEL_POWERPG_CALLBACK => 'PowerPG Callback',
            self::CHANNEL_WITHDRAW => 'Withdraw',
            self::CHANNEL_ADJUSTMENT => 'Adjustment',
            self::CHANNEL_REBATE => 'Rebate',
            self::CHANNEL_COMMISSION => 'Commission',
            self::CHANNEL_BONUS => 'Bonus',
            self::CHANNEL_MALL => 'Mall',
            self::CHANNEL_PROGRESS_DEPOSIT => 'Progress Deposit',
            self::CHANNEL_PROGRESS_WITHDRAW => 'Progress Withdraw',
            self::CHANNEL_DGPIN_DEPOSIT => 'DG Pin Deposit',
            self::CHANNEL_DGPIN_CALLBACK => 'DG Pin Callback',
            self::CHANNEL_SV388 => 'SV388',
            self::CHANNEL_YGR => 'YGR',
            self::CHANNEL_MegaH5 => 'MegaH5',
            self::CHANNEL_MK => 'MK',
            self::CHANNEL_HOGAMING => 'HoGaming',
            self::CHANNEL_YELLOWBAT => 'YellowBat',
            self::CHANNEL_RICH88 => 'Rich88',
            self::CHANNEL_EZUGI => 'Ezugi',
            self::CHANNEL_HOTROAD => 'Hotroad',
            self::CHANNEL_NETENT => 'Netent',
            self::CHANNEL_SPINIX => 'Spinix',
            self::CHANNEL_ACEWIN => 'AceWin',
            self::CHANNEL_CROWDPLAY => 'CrowdPlay',
            self::CHANNEL_FFF => 'FFF',
            self::CHANNEL_REVOLUTION => 'Revolution',
            self::CHANNEL_RELAXGAMING => 'Relax Gaming',
            self::CHANNEL_ALLINONE => 'AllInOne',
            self::CHANNEL_WBET => 'Wbet',
        ];

    protected $fillable = [
        'channel',
        'function',
        'method',
        'params',
        'message',
        'request',
        'status',
        'trace',
    ];

    public static function getDatatables()
    {
        $query = Log::select(
            'id',
            'channel',
            'function',
            'method',
            'params',
            'status',
            'message',
            'trace',
            'created_at',
            'updated_at'
        );

        if (request()->filled('channel') && request()->channel !== '') {
            $query->where('channel', request()->channel);
        }

        if (request()->filled('status') && request()->status !== '') {
            $query->where('status', request()->status);
        }

        if (request()->filled('from_datetime') && request()->filled('to_datetime')) {
            $query->whereBetween('created_at', [request()->from_datetime, request()->to_datetime]);
        }

        return DataTables::of($query)
            ->editColumn('id', function ($log) {
                return $log->id;
            })
            ->editColumn('status', function ($log) {
                return SELF::STATUS[$log->status];
            })
            ->editColumn('api_function', function ($log) {
                return $log->function;
            })
            ->editColumn('channel', function ($log) {
                return SELF::CHANNELS[$log->channel];
            })
            ->editColumn('message', function ($log) {
                if (!empty($log->message)) {
                    return "<a href='#' onclick='openTrace(this)' data-trace='<br><br>Method:" . $log->method . "<br><br>Request:" . $log->params . "<br><br>Response:<br>" . $log->trace . "'>" . $log->message . "</a>";
                } else {
                    return "<a href='#' onclick='openTrace(this)' data-trace='<br><br>Method:" . $log->method . "<br><br>Request:" . $log->params . "<br><br>Response:<br>" . $log->trace . "'>Details</a>";
                }
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at->format('Y-m-d H:i:s');
            })
            ->editColumn('updated_at', function ($log) {
                return $log->created_at->format('Y-m-d H:i:s');
            })
            ->rawColumns(['updated_at', 'message', 'function'])
            ->toJson();
    }

    public static function getDatatablesColumns()
    {
        return [
            Column::make('id')->title(__("id")),
            Column::make('channel')->title(__("Channel")),
            Column::make('api_function')->title(__("API function")),
            Column::make('status')->title(__("Status")),
            Column::make('message')->title(__("Message")),
            Column::make('updated_at')->title(__("Updated At")),
            Column::make('created_at')->title(__("Created At")),
        ];
    }

    // public static function addLog($channel, $url = null, $method = null, $params = null, $message, $id = 0, $status = 0, $trace = null)
    // {
    //     if ($id) {
    //         $logEntry = Log::find($id);
    //         if ($logEntry) {
    //             $logEntry->update([
    //                 'message' => $message,
    //                 'status' => $status,
    //                 'trace' => $trace,
    //             ]);
    //             return $logEntry->id;
    //         }
    //     };

    //     $newLog = Log::create([
    //         'url' => $url,
    //         'method' => $method,
    //         'params' => $params,
    //         'channel' => $channel,
    //         'message' => $message,
    //         'status' => $status,
    //         'trace' => $trace,
    //     ]);

    //     return $newLog->id;
    // }    


    public static function addLog($data)
    {
        $id = $data['id'] ?? 0;
        $channel = $data['channel'] ?? null;
        $url = $data['url'] ?? null;
        $method = $data['method'] ?? null;
        $function = $data['function'] ?? null;
        $params = $data['params'] ?? null;
        $message = $data['message'] ?? null;
        $status = $data['status'] ?? 0;
        $trace = $data['trace'] ?? null;

        if ($id) {
            $logEntry = Log::find($id);
            if ($logEntry) {
                $logEntry->update([
                    'message' => $message,
                    'status' => $status,
                    'trace' => $trace,
                ]);
                return $logEntry->id;
            }
        };

        $newLog = Log::create([
            'url' => $url,
            'method' => $method,
            'function' => $function,
            'params' => $params,
            'channel' => $channel,
            'message' => $message,
            'status' => $status,
            'trace' => $trace,
        ]);

        return $newLog->id;
    }
}
