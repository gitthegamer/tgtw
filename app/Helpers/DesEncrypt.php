<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class DesEncrypt
{
    public static function encrypt($str, $key)
    {
        $str = self::pkcs5Pad($str);
        $encode = mcrypt_encrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
        return base64_encode($encode);
    }

    public static function decrypt($str, $key)
    {
        $str = base64_decode($str);
        $str = mcrypt_decrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
        return $str;
    }

    private static function pkcs5Pad($str)
    {
        $len = strlen($str);
        $mod = $len % 8;
        $pad = 8 - $mod;
        return $str . str_repeat(chr($pad), $pad);
    }

    public static function new_encrypt($str, $key, $iv = null)
    {
        if ($iv === null) {
            $iv = $key;
        };

        return base64_encode(openssl_encrypt($str, 'DES-CBC', $key, OPENSSL_RAW_DATA, $iv));
    }

    public static function cbc_encrypt($str, $key)
    {
        // Pad the string to the appropriate block size
        $blockSize = mcrypt_get_block_size(MCRYPT_DES, MCRYPT_MODE_CBC);
        $padSize = $blockSize - (strlen($str) % $blockSize);
        $str = $str . str_repeat(chr($padSize), $padSize);
        // Encrypt using DES CBC mode
        $encode = mcrypt_encrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_CBC, $key);

        return base64_encode($encode);
    }
}
