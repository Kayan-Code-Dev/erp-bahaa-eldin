<?php

namespace App\Helpers;

class OtpGenerator
{
    /**
     * Generate numeric OTP
     *
     * @param int $length
     * @return string
     */
    public static function generateNumeric(int $length = 6): string
    {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }

    /**
     * Generate alphanumeric OTP
     *
     * @param int $length
     * @return string
     */
    public static function generateAlphanumeric(int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $otp = '';
        $maxIndex = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $otp .= $characters[random_int(0, $maxIndex)];
        }

        return $otp;
    }
}
