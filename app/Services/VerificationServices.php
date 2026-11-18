<?php

namespace App\Services;

use App\Models\User;
use App\Models\User_verfication;


class VerificationServices
{
    /** set OTP code for mobile
     * @param $data
     *
     * @return User_verfication
     */
    public function setVerificationCode($userId, $isResetPassword = false)
    {
        $code = mt_rand(1000, 9999);
        // $expiresAt = now()->addMinutes(5);
        $expiresAt = now()->addSeconds(60);
        $oldCode = User_verfication::where('user_id', $userId)->where('is_reset_password', $isResetPassword)->first();
        $newCode = User_verfication::updateOrCreate([
            'id' => $oldCode ? $oldCode->id : null
        ], [
            'user_id' => $userId,
            'code' => $code,
            'is_reset_password' => $isResetPassword,
            'verification_code_expires_at' => $expiresAt,

        ]);

        return $newCode;
    }

    public function getSMSVerifyMessageByAppName($code)
    {
        $setting = \App\Models\Setting::select('website_name', 'website_name_en')->first();
        $message = $setting->CurrentNameLang . "  رمز التحقق الخاص بحسابك ";
        return $message . $code;
    }

    public function checkOTPCodePassword($user_id, $code, $isResetPassword = false)
    {

        $verificationData = User_verfication::where('user_id', $user_id)
            ->where('is_reset_password', $isResetPassword)->first();
        if ($verificationData) {
            if ($verificationData->code == $code) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }


}
