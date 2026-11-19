<?php

namespace App\Services\SMSGateways;

use App\Models\Setting;
use GuzzleHttp\Client;
use Exception;

class moraSms
{
    public $client;

    public function __construct()
    {
        if (!$this->client) {
            $this->client = new Client();
        }
    }

    public function sendSms($phone, $message, $language = 'en', $model = null)
    {
//        dd('stop send now');
        $setting = Setting::first();

        if (! $setting || empty($setting->sms_api_key) || empty($setting->sms_user_name)) {
            info('Mora API not configured: missing SMS credentials in settings.');
            return false;
        }

        try {
            // API credentials
            $apiKey = $setting->sms_api_key; // Mora API key
            $username = $setting->sms_user_name; // Your username
            $sender = $setting->sms_sender; // Sender name (owner name)
            $phone_number = $phone; // phone of user who will receive sms

            // The Mora API accepts POST with form fields (see its docs:
            // curl -X POST .../sendsms -d "api_key=...&username=...").
            // The key is sent in the request BODY, never in a GET query
            // string, so it cannot leak into access logs or exception
            // messages that echo the URL.
            $response = $this->client->post('https://www.mora-sa.com/api/v1/sendsms', [
                'form_params' => [
                    'api_key' => $apiKey,
                    'username' => $username,
                    'message' => $message,
                    'numbers' => $phone_number,
                    'sender' => $sender,
                    'unicode' => 'e',
                    'return' => 'json',
                ],
            ]);

            // Get the response body
            $content = $response->getBody()->getContents();

            $jsonResponse = json_decode($content, true);

            if (isset($jsonResponse['status']['code']) && $jsonResponse['status']['code'] == 200) {
                return true;
            } else {
                info("Mora API error status or unexpected response!");
                return false;
            }

        } catch (Exception $e) {
            // Log only the exception class — never the message, which for
            // Guzzle errors can embed the request URL (and with it any
            // credentials sent in it) into storage/logs/laravel.log.
            info('Mora API failed to send SMS to ' . $phone . ': ' . class_basename($e));
            return false;
        }
    }
}
