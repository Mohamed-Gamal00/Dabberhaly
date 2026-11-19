<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Models\User_verfication;
use App\Services\SMSGateways\moraSms;
use App\Services\SMSGateways\TelephoneVerification;
use App\Services\UserService;
use App\Services\VerificationServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class UserAuthController extends Controller
{
    protected $sms_service;
    protected $moraSms;
    protected $userService;
    protected $telephoneVerification;

    public function __construct(VerificationServices $services, moraSms $moraSmsGateway, TelephoneVerification $telephoneVerification, UserService $userService)
    {
        $this->sms_service = $services;
        $this->moraSms = $moraSmsGateway;
        $this->userService = $userService;
        $this->telephoneVerification = $telephoneVerification;
    }

    public function register(RegisterRequest $request)
    {
        $user = null;

        $user = $this->userService->createUser($request);

        $data = [
            'verification_required' => true,
            'user_id' => $user->id,
        ];
        return $this->telephoneVerification->SendVerificationCode($user, $data);
    }

    public function verifyCode(Request $request)
    {
        $verificationData = $request->validate([
            'user_id' => 'required',
            'code' => 'required',
        ]);

        $user = User::find($verificationData['user_id']);

        if (!$user) {
            return ApiResponse::sendResponse(404, "User not found");
        }

        // Brute-force guard: at most 10 verification attempts per user
        // per minute, on top of the wrong-code counter below.
        $throttleKey = 'api-otp-verify:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return ApiResponse::sendResponse(429, 'Too many attempts. Please try again later.');
        }
        RateLimiter::hit($throttleKey, 60);

        /*check expired code*/
        if ($this->userService->isVerificationCodeExpired($user)) {
            return ApiResponse::sendResponse(403, "انتهت صلاحية رمز التحقق. يرجى طلب واحد جديدة.");
        }

        $isValidCode = $this->sms_service->checkOTPCodePassword($user->id, $verificationData['code']);

        if ($isValidCode) {
            $this->sms_service->clearOtpAttempts($user->id);
            RateLimiter::clear($throttleKey);
            $this->userService->markUserVerified($user, $request->code);
            $token = $user->createToken('-AuthToken')->plainTextToken;
            $data = [
                'token' => $token,
            ];

            return ApiResponse::sendResponse(200, 'Verification successful', $data);
        } else {
            $attempts = $this->sms_service->recordFailedOtpAttempt($user->id);
            if ($attempts >= VerificationServices::MAX_OTP_ATTEMPTS) {
                return ApiResponse::sendResponse(403, 'Too many wrong attempts. The verification code has been invalidated, please request a new one.');
            }
            return ApiResponse::sendResponse(403, 'Invalid verification code');
        }
    }

    public function login(LoginRequest $request)
    {
        // Brute-force guard: 5 failed logins per phone number + IP, then
        // locked out for a minute (same limit as the web LoginRequest).
        $throttleKey = 'api-login:' . $request->phone_number . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return ApiResponse::sendResponse(429, 'Too many login attempts. Please try again later.');
        }

        $user = User::where('phone_number', $request->phone_number)->first();
        if ($user && Hash::check($request->password, $user->password)) {
            // Accounts registered through the API must verify their OTP
            // before they can log in. Users with no verification row
            // (e.g. registered on the website, where verification happens
            // before the account is created) are not blocked — and note
            // users.is_verified is never set anywhere, so it must not
            // be used as the check here.
            $verification = $user->verificationCode;
            if ($verification && ! $verification->is_verified) {
                return ApiResponse::sendResponse(403, 'Account not verified', [
                    'verification_required' => true,
                    'user_id' => $user->id,
                ]);
            }

            $token = $user->createToken('-AuthToken')->plainTextToken;
            $data = [
                'token' => $token,
            ];

            RateLimiter::clear($throttleKey);

            return ApiResponse::sendResponse(200, 'Login Successfully', $data);
        }
        RateLimiter::hit($throttleKey, 60);
        return ApiResponse::sendResponse(401, 'Login Failed', []);
    }


    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();
        return ApiResponse::sendResponse(200, 'logged out');
    }

    public function forgetPassword(Request $request)
    {
        $user = User::where('phone_number', $request->phone_number)->first();
        if (!$user) {
            return ApiResponse::sendResponse(200, 'this phone_number not exist');
        }
        $data = [
            'verification_required' => true,
            'user_id' => $user->id,
        ];
        return $this->telephoneVerification->SendVerificationCode($user, $data, true);
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code_verify' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('id', $request->user_id)->first();

        // Brute-force guard: at most 10 reset attempts per user per minute.
        $throttleKey = 'api-otp-reset:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return ApiResponse::sendResponse(429, 'Too many attempts. Please try again later.');
        }
        RateLimiter::hit($throttleKey, 60);

        if ($this->userService->isVerificationCodeExpired($user, true)) {
            return ApiResponse::sendResponse(403, "انتهت صلاحية رمز التحقق. يرجى طلب واحد جديدة.");
        }

        $isValidCode = $this->sms_service->checkOTPCodePassword($user->id, $request->code_verify, true);
        if ($isValidCode) {
            $this->sms_service->clearOtpAttempts($user->id, true);
            RateLimiter::clear($throttleKey);
            $user->update(['password' => $request->password]);
            return ApiResponse::sendResponse(200, 'Password reset successfully.');
        } else {
            $attempts = $this->sms_service->recordFailedOtpAttempt($user->id, true);
            if ($attempts >= VerificationServices::MAX_OTP_ATTEMPTS) {
                return ApiResponse::sendResponse(403, 'Too many wrong attempts. The verification code has been invalidated, please request a new one.');
            }
            return ApiResponse::sendResponse(403, 'Invalid verification code');
        }
    }

    public function resendVerifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, "Validation Error", $validator->errors());
        }

        $user_id = $request->user_id;
        $user = User::find($user_id);
        if (!$user) {
            return 'user id not found';
        }

        // Resending a code is itself an OTP action: at most 5 resends per
        // user per minute, so the endpoint cannot be used to spam SMS.
        $throttleKey = 'api-otp-resend:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return ApiResponse::sendResponse(429, 'Too many attempts. Please try again later.');
        }
        RateLimiter::hit($throttleKey, 60);

        $data = ['user_id' => $user->id];
        return $this->telephoneVerification->SendVerificationCode($user, $data);
    }
}
