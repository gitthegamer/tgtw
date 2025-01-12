<?php

namespace App\Helpers;


use App\Http\Helpers;
use App\Models\BetLog;
use App\Modules\_AWCController;
use Illuminate\Support\Carbon;

class _AWC
{
    const PLATFORM = [
        // "SEXYBCRT",
        "JILI",
        "PP",
        "FC",
        "SPADE",
        "JDB"
    ];


    public static function getBets($platform, $date)
    {
        $response = _AWCController::init("fetch/gzip/getTransactionByUpdateDate", [
            'cert' =>  config('api.AWC_CERT'),
            'agentId' =>  config('api.AWC_USER_ID'),
            "timeFrom" => $date,
            'platform' => $platform,
            'currency' =>  config('api.AWC_CURRENCY'),
        ]);

        if (!$response['status']) {
            return false;
        }

        return $response['data']['transactions'];
    }

    public static function getDailyBetLog($platform, $startTime, $endTime)
    {
        $response = _AWCController::init("fetch/getSummaryByTxTimeHour", [
            'cert' =>  config('api.AWC_CERT'),
            'agentId' =>  config('api.AWC_USER_ID'),
            'startTime' => Carbon::parse($startTime)->format('Y-m-d\THP'),
            'endTime' => Carbon::parse($endTime)->format('Y-m-d\THP'),
            'platform' => $platform,
            'currency' =>  config('api.AWC_CURRENCY'),
        ]);

        if (!$response['status']) {
            return [];
        }

        $result = $response['data']['transactions'];
        if (count($result) == 0) {
            return [];
        }

        $betAmount = 0;
        $winAmount = 0;

        foreach ($result as $item) {
            $betAmount += $item['betAmount'];
            $winAmount += $item['winAmount'];
        }

        $local_betAmount = 0;
        $local_winAmount = 0;

        $local_result = BetLog::where('round_at', '>=', Carbon::parse($startTime)->format('Y-m-d\TH:i:s'))
            ->where('round_at', '<', Carbon::parse($endTime)->format('Y-m-d\TH:i:s'))
            ->where('product', $platform)
            ->get();


        if ($local_result != null) {
            $local_betAmount = $local_result->sum('stake');
            $local_winAmount = $local_result->sum('payout');
        }

        $betAmountValue = number_format($betAmount, 2, '.', '');
        $winAmountValue = number_format($winAmount, 2, '.', '');
        $local_betAmountValue = number_format($local_betAmount, 2, '.', '');
        $local_winAmountValue = number_format($local_winAmount, 2, '.', '');

        $betAmount = (float)$betAmountValue;
        $winAmount = (float)$winAmountValue;
        $local_betAmount = (float)$local_betAmountValue;
        $local_winAmount = (float)$local_winAmountValue;


        if ($betAmount !== $local_betAmount && $winAmount !== $local_winAmount) {

            $response = _AWCController::init("fetch/gzip/getTransactionByTxTime", [
                'cert' =>  config('api.AWC_CERT'),
                'agentId' =>  config('api.AWC_USER_ID'),
                'startTime' => Carbon::parse($startTime)->format('Y-m-d\TH:i:sP'),
                'endTime' => Carbon::parse($endTime)->format('Y-m-d\TH:i:sP'),
                'platform' => $platform,
                'currency' =>  config('api.AWC_CURRENCY'),
            ]);


            if (!$response['status']) {
                return [];
            }
            return $response['data']['transactions'];
        }

        // return [];
    }
}
