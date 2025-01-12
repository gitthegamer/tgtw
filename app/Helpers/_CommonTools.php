<?php

namespace App\Helpers;

use Carbon\Carbon;

class _CommonTools
{
    /**
     * 将输入转换为字符串
     * @param mixed $input 输入可以是任何类型
     * @return string
     */
    public static function toString($input)
    {
        return strval($input);
    }

    /**
     * 将输入转换为整数
     * @param mixed $input 输入可以是字符串、浮点数等
     * @return int
     */
    public static function toInt($input)
    {
        return intval($input);
    }
    /**
     * 将输入转换为浮点数
     * @param mixed $input 输入可以是字符串、整数等
     * @return float
     */
    public static function toFloat($input)
    {
        if (is_string($input)) {
            $input = str_replace(',', '', $input); // 移除字符串中的逗号
        }
        return floatval($input);
    }

    /**
     * 转换语言
     * @param string $text
     * @param string $targetLang
     * @return string
     */
    public static function translate($text, $targetLang = 'zh')
    {
        // 假设使用某API进行翻译
        // 这里是一个示例，实际应用中需要调用真实的翻译服务
        return "翻译后的文本"; // 示例返回值
    }

    /**
     * 将时间转换为特定格式
     * @param string $time
     * @param string $format
     * @return string
     */
    public static function formatTime($time, $format = 'Y-m-d H:i:s')
    {
        return Carbon::parse($time)->format($format);
    }

    /**
     * 随机生成用户名，只包含字母，不包含数字和标点符号，可以指定长度范围
     * @param int $minLength 最小长度，默认为8
     * @param int $maxLength 最大长度，默认为8
     * @return string
     */
    public static function randomUsername($minLength = 8, $maxLength = 8)
    {
        //cant use faker due to aws lambda limit file size

        // $faker = Factory::create();
        // // 直接生成指定长度范围内的用户名
        // $username = '';
        // do {
        //     // 生成一个随机字母字符串
        //     $username .= $faker->lexify('????????'); // ? 代表随机字母
        //     // 清除非字母字符
        //     $username = preg_replace('/\W/', '', $username);
        //     // 截断到最大长度
        //     if (strlen($username) > $maxLength) {
        //         $username = substr($username, 0, $maxLength);
        //     }
        // } while (strlen($username) < $minLength);

        // return $username;
    }

    /**
     * 随机生成手机号码，01开头，并可以指定号码长度
     * @param int $length 号码总长度，默认为10
     * @return string
     */
    public static function randomPhone($length = 10)
    {
        $numberLength = $length - 2; // 减去'01'的长度
        $min = pow(10, $numberLength - 1);
        $max = pow(10, $numberLength) - 1;
        $number = rand($min, $max);
        // 使用 str_pad 确保号码长度正确
        $formattedNumber = str_pad($number, $numberLength, "0", STR_PAD_LEFT);
        return '01' . $formattedNumber;
    }

    /**
     * 生成指定长度的随机密码。
     * 确保包含大写字母、小写字母和数字。
     * @param int $len 密码的期望长度，默认为8
     * @return string 生成的密码
     */
    public static function randomPassword($len = 8)
    {
        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghjkmnpqrstuvwxyz',
            '123456789'
        ];

        $all = ''; // 所有可能的字符
        $password = ''; // 最终的密码

        // 收集所有可能的字符
        foreach ($sets as $set) {
            $all .= $set;
            $password .= $set[array_rand(str_split($set))];
        }

        // 生成密码的其余部分
        for ($i = strlen($password); $i < $len; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // 在返回前打乱密码字符串！
        return str_shuffle($password);
    }
}
