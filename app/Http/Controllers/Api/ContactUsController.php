<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Requests\Api\ContactUsRequest;
use App\Models\Admin;
use App\Models\ContactUs;
use App\Notifications\ContactMessageAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Http\Controllers\Controller;


class ContactUsController extends Controller
{
    public function sendMessage(ContactUsRequest $request)
    {
        $request->validated();
        $form = ContactUs::create([
            'name' => $request->full_name,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
            'message' => $request->message
        ]);

        $admins = Admin::all();

        if ($form) {
            foreach ($admins as $admin) {
                try {
                    $admin->notify(new ContactMessageAdminNotification($form));
                } catch (\Exception $e) {
                    \Log::error('Error notifying admin ' . $admin->email . ': ' . $e->getMessage());
                }
            }

            return ApiResponse::sendResponse(200, 'Message Sent Successfully', []);
        }
        return ApiResponse::sendResponse(200, 'Cannot Send Message', []);
    }
}
