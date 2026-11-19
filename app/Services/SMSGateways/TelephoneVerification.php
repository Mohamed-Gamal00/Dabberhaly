<?php

namespace App\Services\SMSGateways;

use App\Helper\ApiResponse;

use App\Services\VerificationServices;

class TelephoneVerification
{
    protected $sms_service;
    protected $moraSms;

    public function __construct(VerificationServices $services, moraSms $moraSmsGateway)
    {
        $this->sms_service = $services;
        $this->moraSms = $moraSmsGateway;
    }

    public function SendVerificationCode($user, $data, $isResetPassword = false)
    {
        $verificationData = $this->sms_service->setVerificationCode($user->id, $isResetPassword);
        $message = $this->sms_service->getSMSVerifyMessageByAppName($verificationData->code);

        // Actually send the SMS — the old code hardcoded $smsSent = true
        // and never called the gateway, so no SMS ever went out.
        $smsSent = $this->moraSms->sendSms($user->phone_number, $message);

        if ($smsSent) {
            // NEVER return the code itself: the old code put the full SMS
            // text (code included) in the JSON response, so anyone could
            // request a code for a victim's number and read it straight
            // from the response. Only a generic "code sent" message goes out.
            return ApiResponse::sendResponse(200, __('flash.otp_sent'), $data);
        } else {
            return ApiResponse::sendResponse(500, __('flash.sms_send_failed'));
        }
    }
}
