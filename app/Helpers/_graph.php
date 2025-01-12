<?php

namespace App\Helpers;

use App\Http\Helpers;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class _graph
{
    public static function overall_deposit_days()
    {
        $result = [
            'x' => [],
            'y' => [],
        ];
        for ($i = 0; $i < 7; $i++) {
            $transactions = DB::table('transactions');
            $transactions->select(
                DB::raw("SUM(IF(`transactions`.`type` = '" . Transaction::TYPE_DEPOSIT . "' AND `transactions`.`status` = '" . Transaction::STATUS_SUCCESS . "', `transactions`.`amount`, 0)) as deposit_total_amount"),
            );
            $transactions->where('transactions.created_at', '>=', Carbon::now()->subDay(6 - $i)->startOfDay());
            $transactions->where('transactions.created_at', '<=', Carbon::now()->subDay(6 - $i)->endOfDay());
            $deposit_total_amount = $transactions->first()->deposit_total_amount ?? 0;

            $result['x'][] = Carbon::now()->subDay(6 - $i)->format('m-d');
            $result['y'][] = (float) $deposit_total_amount;
        }

        return $result;
    }

    public static function overall_deposit_hours()
    {
        // top hour deposit number
        $result = [
            'x' => [],
            'y' => [],
        ];
        // Loop through each hour of the day
        for ($i = 0; $i < 13; $i++) {
            $transactions = DB::table('transactions');
            $transactions->select(
                DB::raw("SUM(IF(`transactions`.`type` = '" . Transaction::TYPE_DEPOSIT . "' AND `transactions`.`status` = '" . Transaction::STATUS_SUCCESS . "', 1, 0)) as deposit_success")
            );
            $transactions->where('transactions.created_at', '>=', Carbon::now()->copy()->startOfHour()->subHours(12 - $i));
            $transactions->where('transactions.created_at', '<=', Carbon::now()->copy()->startOfHour()->subHours(11 - $i));
            $total_deposit_success = $transactions->first()->deposit_success ?? 0;

            if ($total_deposit_success == 0) {
                continue;
            }
            // $time = Carbon::createFromTime($i);
            $result['x'][] = Carbon::now()->copy()->startOfHour()->subHours(12 - $i)->format('ga');
            $result['y'][] = (int) $total_deposit_success;
        }

        return $result;
    }

    public static function overall_game_played()
    {
        $colors = [
            'rgba(255, 99, 132, 0.5)',
            'rgba(54, 162, 235, 0.5)',
            'rgba(255, 206, 86, 0.5)',
            'rgba(75, 192, 192, 0.5)',
            'rgba(153, 102, 255, 0.5)',
            'rgba(255, 159, 64, 0.5)',
            'rgba(100, 100, 255, 0.5)',
            'rgba(200, 50, 150, 0.5)',
            'rgba(50, 200, 100, 0.5)',
            'rgba(150, 150, 50, 0.5)',
        ];
        $result = [
            'title' => '# number of members',
            'label' => [],
            'count' => [],
            'color' => $colors,
        ];

        $startOfMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::now()->endOfMonth()->format('Y-m-d');

        $mostPlayedGames = ProductReport::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->select('product_id', DB::raw('COUNT(DISTINCT member_id) as member_count'))
            ->groupBy('product_id')
            ->take(10)
            ->get();

        if ($mostPlayedGames) {
            foreach ($mostPlayedGames as $mostPlayedGame) {
                $mostPopularProductId = $mostPlayedGame->product_id;
                $product = _CommonCache::product($mostPopularProductId);
                $memberCount = $mostPlayedGame->member_count;
                $result['label'][] = $product->name;
                $result['count'][] = (int) $memberCount;
            }
        }

        return $result;
    }

    public static function overall_category_played()
    {
        $colors = [
            'rgba(255, 99, 132, 0.5)',
            'rgba(54, 162, 235, 0.5)',
            'rgba(255, 206, 86, 0.5)',
            'rgba(75, 192, 192, 0.5)',
            'rgba(153, 102, 255, 0.5)',
            'rgba(255, 159, 64, 0.5)',
            'rgba(100, 100, 255, 0.5)',
            'rgba(200, 50, 150, 0.5)',
            'rgba(50, 200, 100, 0.5)',
            'rgba(150, 150, 50, 0.5)',
        ];
        $result = [
            'title' => '# number of members',
            'label' => [],
            'count' => [],
            'color' => $colors,
        ];

        $startOfMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::now()->endOfMonth()->format('Y-m-d');

        $mostPlayedGames = ProductReport::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->select('category', DB::raw('COUNT(DISTINCT member_id) as member_count'))
            ->groupBy('category')
            ->take(10)
            ->get();

        if ($mostPlayedGames) {
            foreach ($mostPlayedGames as $mostPlayedGame) {

                if ($mostPlayedGame->category == null) {
                    continue;
                }
                $memberCount = $mostPlayedGame->member_count;
                $result['label'][] = Product::CATEGORY[$mostPlayedGame->category];
                $result['count'][] = (int) $memberCount;
            }
        }

        return $result;
    }

    public static function overall_member_register()
    {
        // top hour deposit number
        $result = [
            'x' => [],
            'y' => [],
        ];
        // Loop through each hour of the day
        for ($i = 0; $i < 13; $i++) {


            // Query the database for members registered within the current hour
            $memberCount = DB::table('members')
                ->where('members.created_at', '>=', Carbon::now()->copy()->startOfHour()->subHours(12 - $i))
                ->where('members.created_at', '<=', Carbon::now()->copy()->startOfHour()->subHours(11 - $i))
                ->count();


            // $time = Carbon::createFromTime($i);
            $result['x'][] = Carbon::now()->copy()->startOfHour()->subHours(12 - $i)->format('ga');
            $result['y'][] = (int) $memberCount;
        }

        return $result;
    }
}
