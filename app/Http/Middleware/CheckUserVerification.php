<?php

namespace App\Http\Middleware;

use App\Models\User_verfication;
use App\Services\SMSGateways\moraSms;
use App\Services\VerificationServices;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserVerification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public $sms_service;
    public $moraGateway;

    public function __construct(VerificationServices $services, moraSms $moraSms)
    {
        $this->sms_service = $services;
        $this->moraGateway = $moraSms;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->verificationCode && !$user->verificationCode->is_verified) {
            // Only send a fresh code when the user has no live one:
            // the code lives 60 seconds, and regenerating it on every
            // request spams the user with SMS and resets the
            // wrong-attempt counter each time. A live code is still
            // usable, so just block with the same 403 and let the
            // user enter the code they already received.
            $liveCode = User_verfication::where('user_id', $user->id)
                ->where('is_reset_password', false)
                ->whereNotNull('verification_code_expires_at')
                ->where('verification_code_expires_at', '>', now())
                ->exists();

            if ($liveCode) {
                return response()->json([
                    'message' => "Your account is not verified. A verification code has already been sent to your phone number.",
                    'verification_required' => true,
                    'user_id' => $user->id,
                ], 403);
            }

            // Generate the verification code and SMS
            $verificationData = $this->sms_service->setVerificationCode($user->id);
            $message = $this->sms_service->getSMSVerifyMessageByAppName($verificationData->code);
            $smsSent = $this->moraGateway->sendSms($user->phone_number, $message);

            if ($smsSent) {
                return response()->json([
                    'message' => "Your account is not verified. A new verification code has been sent to your phone number.",
                    'verification_required' => true,
                    'user_id' => $user->id,
                ], 403);
            } else {
                return response()->json([
                    'message' => 'Failed to send verification SMS. Please try again later.',
                ], 500);
            }
        }

        // Allow the request to continue to the next middleware or controller
        return $next($request);
    }
}
