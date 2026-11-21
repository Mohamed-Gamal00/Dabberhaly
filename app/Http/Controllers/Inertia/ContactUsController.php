<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ContactUs;
use App\Models\Setting;
use App\Notifications\ContactMessageAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ContactUsController extends Controller
{
    public function index()
    {
        $setting = Setting::first();

        return Inertia::render('ContactUs/Index', [
            'contact' => [
                'phone' => $setting?->phone_number ?? $setting?->phone,
                'email' => $setting?->email,
                'address' => $setting?->address,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name'     => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'phone_number'  => ['required', 'regex:/^[0-9+\s\-]{7,20}$/'],
            'text'          => 'required|string',
        ]);

        $form = ContactUs::create([
            'name'         => $request->full_name,
            'phone_number' => $request->phone_number,
            'email'        => $request->contact_email,
            'message'      => $request->text,
        ]);

        $admins = Admin::all();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new ContactMessageAdminNotification($form));
            } catch (\Exception $e) {}
        }

        return redirect()->back()->with('success', __('flash.contact_sent'));
    }
}
