<?php

namespace App\Modules;

use App\Models\Log as ModelsLog;
use Exception;
use Illuminate\Support\Facades\Log;

class _918KayaController
{
    protected $agentID;
    protected $accountName;
    protected $accountPW;
    protected $accountDisplay;
    protected $timeStamp;
    protected $transAmount;
    protected $transAgentID;
    protected $gamePlatformID;
    protected $gameID;
    protected $lang;
    protected $menu;
    protected $startUpdateTime;
    protected $endUpdateTime;

    public static function init($function, $params)
    {
        $controller = new _918KayaController();
        return $controller->request($function, $params);
    }

    public function create_param($function, $params)
    {
        foreach ($params as $param => $value) {
            $this->{$param} = $value;
        }
    }

    public function get_url($function)
    {
        return config('api.918KAYA_API_LINK') . $function;
    }

    public function make_params($function)
    {
        switch ($function) {
            case "v1/accountcreate":
                return [
                    'agentID' => $this->agentID,
                    'accountName' => $this->accountName,
                    'accountPW' => $this->accountPW,
                    'accountDisplay' => $this->accountDisplay,
                    'timeStamp' => $this->timeStamp,
                ];
            case "v1/accountbalance":
                return [
                    'agentID' => $this->agentID,
                    'accountName' => $this->accountName,
                    'timeStamp' => $this->timeStamp,
                ];
            case "v1/transferdeposit":
                return [
                    'agentID' => $this->agentID,
                    'accountName' => $this->accountName,
                    'transAmount' => $this->transAmount,
                    'transAgentID' => $this->transAgentID,
                    'timeStamp' => $this->timeStamp,
                ];
            case "v1/transferwithdraw":
                return [
                    'agentID' => $this->agentID,
                    'accountName' => $this->accountName,
                    'transAmount' => $this->transAmount,
                    'transAgentID' => $this->transAgentID,
                    'timeStamp' => $this->timeStamp,
                ];
            case "v1/transfercheck":
                return [
                    'agentID' => $this->agentID,
                    'accountName' => $this->accountName,
                    'transAmount' => $this->transAmount,
                    'transAgentID' => $this->transAgentID,
                    'timeStamp' => $this->timeStamp,
                ];
            case "v1/launchH5":
                return [
                    'agentID' => $this->agentID,
                    'accountName' => $this->accountName,
                    'gamePlatformID' => $this->gamePlatformID,
                    'gameID' => $this->gameID,
                    'lang' => $this->lang,
                    'menu' => $this->menu,
                ];
            case "v1/betlist":
                return [
                    'agentID' => $this->agentID,
                    'startUpdateTime' => $this->startUpdateTime,
                    'endUpdateTime' => $this->endUpdateTime,
                    'timeStamp' => $this->timeStamp,
                ];
        }
    }

    public function request($function, $params)
    {
        $time = time();
        $method = "POST";
        $logForDB = [
            'channel' => ModelsLog::CHANNEL_918Kaya,
            'function' => $function,
            'params' => json_encode($params),
            'method' => $method,
        ];


        $log = '918kaya_api_records';
        if ($function == "v1/betlist") {
            $log = '918kaya_api_ticket_records';
        }
        if ($function == "v1/transferdeposit" || $function == "v1/transferwithdraw" || $function == "v1/transfercheck") {
            $log = '918kaya_api_transfer_records';
        }
        if ($function == "v1/accountbalance") {
            $log = '918kaya_api_balance_records';
        }
        Log::channel($log)->debug("$time Function : " . $function);
        $this->create_param($function, $params);
        Log::channel($log)->debug("$time Params : " . json_encode($params));
        $response = false;

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($this->get_url($function), [
                'headers' => [
                    'AES-ENCODE' => SELF::aes_encode($function, $this->make_params($function)),
                    'Accept-Encoding' => 'gzip',
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($time, $log) {

                    Log::channel($log)->debug("$time URL: " . $stats->getEffectiveUri());
                    Log::channel($log)->debug("$time Time: " . $stats->getTransferTime());
                },
                'body' => json_encode($this->make_params($function)),
            ]);

            $response = @json_decode($response->getBody(), true);
            $logForDB['status'] = ModelsLog::STATUS_SUCCESS;
            $logForDB['trace'] = json_encode($response);
            Log::channel($log)->debug("$time Response: " . json_encode($response));
        } catch (Exception $e) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['trace'] = $e->getMessage();
            ModelsLog::addLog($logForDB);
            Log::channel($log)->debug("$time " . "Unknown ERROR" . "$e");
            Log::channel($log)->error("Error: " . $e->getMessage());
            Log::channel($log)->error("Stack Trace: " . $e->getTraceAsString());
            return [
                'status' => false,
                'status_message' => "Unknown ERROR",
                'data' => null,
            ];
        }

        if (!$response) {
            Log::channel($log)->debug("$time Status: Unknown");

            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            $logForDB['message'] = "$time Status: Unknown";
            ModelsLog::addLog($logForDB);

            return [
                'status' => false,
                'status_message' => "Connection Error",
                'data' => null,
            ];
        }

        if ($function == "v1/launchH5") {
            if ($response['errorCode'] !== 0) {
                $logForDB['status'] = ModelsLog::STATUS_ERROR;
                ModelsLog::addLog($logForDB);
            }

            return [
                'status' => $response['errorCode'] === 0 ? true : false,
                'status_message' => SELF::ERRORS[$response['errorCode']] ?? ($response['msg'] ?? "NO MSG"),
                'data' => $response
            ];
        }

        if (isset($response['rtStatus'], $response['errorCode']) && $response['rtStatus'] !== 1 && $response['errorCode'] != 903 && $response['errorCode'] != 996) {
            $logForDB['status'] = ModelsLog::STATUS_ERROR;
            ModelsLog::addLog($logForDB);
        }

        $status = false;
        if (isset($response['rtStatus']) && $response['rtStatus'] === 1) {
            $status = true;
        }
        if (isset($response['errorCode']) && ($response['errorCode'] == 903 || $response['errorCode'] == 996)) {
            $status = true;
        }

        return [
            'status' => $status,
            'status_message' => isset($response['errorCode']) ? (SELF::ERRORS[$response['errorCode']] ?? ($response['msg'] ?? "NO MSG")) : "NO MSG",
            'data' => $response
        ];
    }

    public static function prepare_sign($function, $data)
    {
        switch ($function) {
                // case 'v1/accountcreate':
                //     return json_encode($data);
                //     break;

            default:
                return json_encode($data);
                break;
        }
    }

    public static function aes_encode($function, $data)
    {
        $encrypt = SELF::encrypt(SELF::prepare_sign($function, $data), config('api.918KAYA_AES_KEY'));
        $data = base64_encode($encrypt);
        return md5($data . config('api.918KAYA_MD5_KEY'));
    }

    public static function encrypt($input, $key)
    {
        $cipher = "aes-128-ecb";
        $data = openssl_encrypt($input, $cipher, $key, OPENSSL_PKCS1_PADDING);
        return $data;
    }

    const ERRORS = [
        900 => 'Verify Code Error - Use wrong way or key to encrypt verify code',
        902 => 'Create Failure, agent id does not exist - Check your agent id',
        903 => 'Create Failure, member account name exist - Use different account name, or check before create',
        904 => 'Create Failure, wrong format or invalid length with member account - Use letter or number to create member account, 6 to 16 characters',
        905 => 'Create Failure, wrong format or invalid length with display name - Use letter or number to create member display name, 6 to 64 characters',
        906 => 'Create Failure, wrong password format - Password must be 6 to 64 characters',
        907 => 'Create Failure, wrong member status format - Member status must be 0 or 1',
        909 => 'Update Failure, member account does not exist - Member account not exist',
        910 => 'Update Failure, amount must be positive - Update amount must be positive number',
        911 => 'Update Failure, wrong amount before transaction - Check amount before transaction',
        912 => 'Update Failure, wrong amount after transaction - Check amount after transaction',
        916 => 'Transaction Number already exist - Transaction Number must be unique',
        917 => 'Transaction Number cannot be empty - Must provide unique Transaction Number',
        918 => 'Wrong period - Period must in 5 minutes',
        919 => 'Transaction Number does not exist - Check Transaction Number',
        920 => 'Page Index does not exist - Check Page Index',
        921 => 'Wrong Page Count - Page Count must below 1000',
        922 => 'Wrong inquiry mode - Check inquiry mode parameter',
        924 => 'Cannot login to this Game Platform - Game Platform does not Enable',
        925 => 'Update failed, member not exist - Check member account',
        926 => 'Update failure, member account - Check member account in particular Game Platform',
        927 => 'Command does not exist - Check available command',
        928 => 'Create member account failure - Check member account name',
        929 => 'Account transfer result not confirm - Please double check this transaction result',
        930 => 'Transaction result not success - Check return parameter rtCheck',
        931 => 'Inquiry member balance failure - Check Inquiry member balance parameter',
        932 => 'Inquiry Bet Log Failure - Check Inquiry Bet Log parameter',
        933 => 'Must set available Agent IP - Please notify 24/7 support center',
        934 => 'Invalid Agent IP - Please notify 24/7 support center',
        935 => 'Invalid Agent Setting - Please notify 24/7 support center',
        937 => 'Login Failure - Check password or account name',
        938 => 'Inquiry bet log Failure - Check inquiry bet log parameter',
        939 => 'Unable to create member account - Check account length or format',
        940 => 'Exception Null Pointer - Transaction number must be Integer',
        941 => 'Update password failure - Check password length or format',
        942 => 'System access failure - Check API connection',
        943 => 'System under maintenance - Please wait till system service online',
        944 => 'Failure to get account and password - Check inquiry parameter',
        945 => '3rd Party Config setting cannot be empty - Please notify 24/7 support center',
        946 => 'DB Dao Save Failure - Please notify 24/7 support center',
        947 => 'DB Dao Update Failure - Please notify 24/7 support center',
        948 => 'DB Dao Occur Exception error - Please notify 24/7 support center',
        949 => 'DB Dao Data not exist - Please notify 24/7 support center',
        950 => 'DB Dao Data cannot update at the same time - Please notify 24/7 support center',
        951 => 'DB Dao FindOne Failure - Please notify 24/7 support center',
        952 => 'DB busy, inquiry failed - Please shorten the query time',
        953 => 'Agent ID cannot be empty - Check agent ID',
        954 => 'Redis Service Error - Please notify 24/7 support center',
        955 => 'Redis Key Exists - Please notify 24/7 support center',
        956 => 'AES Encryption Failure - Check AES encryption',
        957 => 'AES or MD5 key can\'t be Null - Check AES & MD5 Key Value',
        958 => 'Agent Config Service Error - Please notify 24/7 support center',
        959 => 'Game Service Error - Please notify 24/7 support center',
        960 => 'Third-party Game platform Config Error - Please notify 24/7 support center',
        961 => 'Game Platform Error - Please notify 24/7 support center',
        962 => 'Member Account or Password Error - Member Account and Password must matches',
        963 => 'Agent Config URL can\'t be Null - Please notify 24/7 support center',
        964 => 'Agent Lobby URL can\'t be Null - Please notify 24/7 support center',
        965 => 'Agent Backend URL can\'t be Null - Please notify 24/7 support center',
        966 => 'Agent Lobby URL doesn\'t Match - Check your Lobby URL Setting',
        967 => 'Insufficient Agent Cash Balance - Please notify 24/7 support center',
        968 => 'Member Account doesn\'t Exist - Must use Registered Member Account',
        969 => 'Member Password Error - Must use Registered Member Password',
        970 => 'Parameter Error - Check Composition of Parameter',
        971 => 'Other Error - Please notify 24/7 support center',
        972 => 'Agent Info is Null - Please notify 24/7 support center',
        973 => 'SQL Function Error - Please notify 24/7 support center',
        974 => 'Client Header AES can\'t be Null - Please notify 24/7 support center',
        975 => 'Agent Rate can\'t be Null - Please notify 24/7 support center',
        976 => 'Redis Key Overflow - Current Request can\'t over three',
        977 => 'Check Date Scope Error - Inquiry period: 5 minutes in last 7 days.',
        978 => 'Game platform is Null - Check Game Platform ID',
        979 => 'Client Service API Error - Please notify 24/7 support center',
        980 => 'Login game Fail - Check Game Code',
        981 => 'Agent Transaction Number not Exist - Check Transaction Number',
        982 => 'DNS CNAME Lookup Text Error - Please notify 24/7 support center',
        983 => 'Agent Gameplatform Closed - Game Platform doesn\'t open to agent',
        984 => 'Query Gtreport Error - Please try to change inquire period shorter',
        985 => 'Payoutfield Format Error - Check payout type parameter',
        986 => 'Get Jackpot Service Error - Please notify 24/7 support center',
        987 => 'Jackpot Value is Empty - Please notify 24/7 support center',
        988 => 'No Jackpot Config - Check jpName was correct',
        989 => 'Jackpot Name can\'t be Null - Must send jpName to get jackpot value',
        990 => 'No Jackpot API URL Config - Check jpName was correct',
        991 => 'No Agent Data - Must send correct agent id',
        992 => 'No Agent Quota Data - No quota value, please notify 24/7 support center',
        993 => 'Agent Up Level Config can\'t be Empty - Please notify 24/7 support center',
        994 => 'Agent Config setting can\'t be Empty - Please notify 24/7 support center',
        995 => 'Kick Failed - Member not online',
        996 => 'Bet Log Not Found - Please try other time period',
        997 => 'Account is Empty - Must send account id',
        998 => 'Can Not Found Any Account Online - Cannot found any account online or system under maintenance',
        999 => 'Account Not Found Online - Cannot found account online or system under maintenance',
        1000 => 'No Agent\'s Account Online - None Agent\'s account online',
        1001 => 'Account not in Game - Account not online',
        1002 => 'Inquiry period limit error - The query time range must be 10 seconds before the current time',
        1003 => 'Exceeded the maximum connection setting - Please try again later',
        1004 => 'DB Query Exception Error - Please notify 24/7 support center',
        1005 => 'Jackpot summary value is empty - Please check jp name was correct',
        1006 => 'Query member report error - Please notify 24/7 support center',
        1007 => 'Too many accounts - Member report query limited to 1000 accounts',
        1009 => 'Agent ID not exist or suspended - Please check agent ID was correct',
        1010 => 'Backend API not response - Please notify 24/7 support center',
        1011 => 'transAgentID can\'t be null - Please provide unique transfer ID',
        1012 => 'Bet Detail Service error - Please notify 24/7 support center',
        1013 => 'Agent service error - Please notify 24/7 support center',
        1034 => 'Agent was suspended - Please check your agent account status via your account manager',
        1035 => 'Insufficient account balance - Please check member account has enough balance',
        1036 => 'OCM2 Rest Service waiting for connection - Please confirm your service network status or contact the 24-hour support center',
        1037 => 'Password must not be null - Please send the account password',
        1038 => 'Display name cannot be null - Please send the account display name',
        1039 => 'API status closed - Please consult your broker to confirm the status of the agent\'s API service',
        1040 => 'Member status update failed - Please confirm member status',
        1041 => 'Time range must not be null - Please enter the time range of the query',
        1042 => 'OCM2 Rest 7110 Service waiting for connection - The system may be under maintenance, please contact the 24-hour support center',
        1043 => 'Members cannot withdraw money in the game - Please make sure account has log out from game before making a withdrawal request',
        1044 => 'Too close to the current time, please adjust to 30 minutes ago - The request time must be 30 minutes ago',
        1045 => 'Please adjust to within 24 hours - The request time must be within 24 hours',
        1046 => 'Please adjust to the time within the last 60 days - The request time must be within the last 60 days',
        1047 => 'Query member account report empty - Please check member account was correct',
        1048 => 'Query period must in hours - Please check period must in hours',
        1049 => 'Timestamp format error - Please check timestamp format must in unix time',
        1050 => 'Time Zone format error - Please check time zone format',
        1051 => 'Time Zone range error - Please check time zone range',
        1052 => 'Trigger withdrawal risk control, please contact the management center - Account lifetime winning amount reached withdrawal limit, please contact support center',
        1053 => 'The member account is restricted and cannot take any transaction - Account withdraw disabled, please contact support center'
    ];
}
