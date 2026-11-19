<?php

namespace App\Services;

use App\Models\User;
use App\Models\User_verfication;
use Illuminate\Support\Facades\Cache;


class VerificationServices
{
    /**
     * Wrong-code attempts allowed before the code is invalidated.
     */
    public const MAX_OTP_ATTEMPTS = 5;

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

        // A fresh code resets the wrong-attempt counter.
        $this->clearOtpAttempts($userId, $isResetPassword);

        return $newCode;
    }

    /**
     * Create a password-reset code synchronously (web flow).
     *
     * This must NOT live inside the queued SendPasswordResetCode listener:
     * without a running queue worker the listener never runs and no code
     * is ever created, so password reset is dead. The listener only SENDS
     * the code that was already created here.
     */
    public function createPasswordResetCode($userId): string
    {
        $code = str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        User_verfication::updateOrCreate(
            ['user_id' => $userId, 'is_reset_password' => true],
            [
                'code' => $code,
                'is_verified' => false,
                'verification_code_expires_at' => now()->addMinutes(10),
            ]
        );

        $this->clearOtpAttempts($userId, true);

        return $code;
    }

    public function otpAttemptsKey($userId, $isResetPassword = false): string
    {
        return 'otp-attempts:' . ($isResetPassword ? 'reset' : 'verify') . ':' . $userId;
    }

    public function failedOtpAttempts($userId, $isResetPassword = false): int
    {
        return (int) Cache::get($this->otpAttemptsKey($userId, $isResetPassword), 0);
    }

    public function clearOtpAttempts($userId, $isResetPassword = false): void
    {
        Cache::forget($this->otpAttemptsKey($userId, $isResetPassword));
    }

    /**
     * Record one wrong-code attempt. Once the limit is reached the code
     * itself is invalidated (deleted) so it can never be guessed later,
     * and the counter is cleared. Returns the attempt count after this hit.
     */
    public function recordFailedOtpAttempt($userId, $isResetPassword = false): int
    {
        $key = $this->otpAttemptsKey($userId, $isResetPassword);
        $attempts = (int) Cache::get($key, 0) + 1;

        if ($attempts >= self::MAX_OTP_ATTEMPTS) {
            User_verfication::where('user_id', $userId)
                ->where('is_reset_password', $isResetPassword)
                ->delete();
            Cache::forget($key);
        } else {
            // Counter lives as long as a code could: 10 minutes covers
            // both the 60-second API code and the 10-minute web reset code.
            Cache::put($key, $attempts, now()->addMinutes(10));
        }

        return $attempts;
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
