<?php

namespace App\Helpers;

use App\Models\ReportPromotionPerformance;
use Carbon\Carbon;

class _CommonFunction
{
    public static function insert_data_into_promotion_performance_report($member, $promotion_id, $withdrawal_amount = 0)
    {
        if ($withdrawal_amount > 0) {
            ReportPromotionPerformance::create([
                'promotion_id' => $promotion_id,
                'top_agent_id' => $member->top_agent_id ?? 0,
                'agent_id' => $member->agent_id ?? 0,
                'upline_id' => $member->upline_id ?? 0,
                'member_id' => $member->id,
                'withdrawal_amount' => $withdrawal_amount,
                'date' => Carbon::now()->format('Y-m-d')
            ]);
        }
    }

    public static function deactivate_member_bonus($member, $message = '')
    {
        $activeBonuses = $member->member_active_bonuses()->get();
        if (count($activeBonuses) > 0) {
            foreach ($activeBonuses as $bonus) {
                $bonus->redeem($message);
                $member->member_logs()->create([
                    'action_by' => 'SYSTEM',
                    'text' => $message
                ]);
            }
        }

        $freeCreditBonus = $member->member_free_credit_bonuses()->get();
        if (count($freeCreditBonus) > 0) {
            foreach ($freeCreditBonus as $bonus) {
                $bonus->redeem($message);
                $member->member_logs()->create([
                    'action_by' => 'SYSTEM',
                    'text' => $message
                ]);
            }
        }
    }

    public static function generate6DigitsPassword()
    {
        $letters = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
        $numbers = substr(str_shuffle('0123456789'), 0, 3);
        return $letters[0] . $numbers[0] . $letters[1] . $numbers[1] . $letters[2] . $numbers[2];
    }
}
