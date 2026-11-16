<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ContactUs;
use App\Notifications\ContactMessageAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ContactUsController extends Controller
{
    public function index()
    {
        return Inertia::render('ContactUs/Index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name'     => 'required|string|max:255',
            'contact_email' => 'required|email',
            'phone_number'  => 'required|numeric',
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
