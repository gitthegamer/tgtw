<?php

namespace App\Helpers;

class Website
{
    public static function getIp()
    {
        return (isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'] ?? "localhost");
    }

    public static function number_format($value, $negative = false)
    {
        $primary_color = "blue";
        $secondary_color = "red";
        if ($negative) {
            $primary_color = "red";
            $secondary_color = "blue";
        }

        if ($value > 0) {
            return "<span style='color:$primary_color'>" . number_format($value, 2) . "</span>";
        } elseif ($value < 0) {
            return "<span style='color:$secondary_color'>" . number_format($value, 2) . "</span>";
        } else {
            return number_format($value, 2);
        }
    }

    public static function modelToArray($datas, $id, $name)
    {
        $output = [];
        foreach ($datas as $data) {
            $output[$data->{$id}] = $data->{$name};
        }
        return $output;
    }

    public static function getBanks($member)
    {
        return $member->member_currency->getBanks();
    }

    public static function bank_lists()
    {
        $bank = [
            "RHB Bank" => asset('images/banks-icon/RHB.png'),
            "Hong Leong Bank" => asset('images/banks-icon/HLB.png'),
            "Ambank" => asset('images/banks-icon/AMB.png'),
            "HSBC Bank" => asset('images/banks-icon/HSBC.png'),
            "Bank Islam" => asset('images/banks-icon/BIMB.png'),
            "Affin Bank" => asset('images/banks-icon/AFB.png'),
            "Alliance Bank" => asset('images/banks-icon/ALB.png'),
            "Argo Bank" => asset('images/banks-icon/AGO.png'),
            "OCBC Bank" => asset('images/banks-icon/OCBC.png'),
            "UOB Bank" => asset('images/banks-icon/UOB.png'),
            "Standard Chartered" => asset('images/banks-icon/SCBL.png'),
            "Maybank" => asset('images/banks-icon/MBB.png'),
            "CIMB Bank" => asset('images/banks-icon/CIMB.png'),
            "Public Bank" => asset('images/banks-icon/PBB.png'),
            "BSN" => asset('images/banks-icon/BSN.png'),
            "Bank Rakyat" => asset('images/banks-icon/BR.png'),
            "Touch & Go" => asset('images/banks-icon/TNG.png'),
        ];
        ksort($bank);
        return $bank;
    }
}
