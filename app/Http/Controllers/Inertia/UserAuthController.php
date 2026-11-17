<?php

namespace App\Http\Controllers\Inertia;

use App\Events\UserForgotPassword;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Models\User_verfication;
use App\Models\UserAddress;
use App\Services\CheckOut\CheckoutServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserAuthController extends Controller
{
    protected CheckoutServices $checkoutServices;

    public function __construct(CheckoutServices $checkoutServices)
    {
        $this->checkoutServices = $checkoutServices;
    }

    /* ================== AUTH VIEWS ================== */

    public function loginView()
    {
        return Inertia::render('Auth/Login');
    }

    public function registerView()
    {
        $countries   = Country::where('status', 'used')->get();
        $countryIds  = Country::where('status', 'used')->pluck('id')->toArray();
        $cities      = City::whereIn('country_id', $countryIds)->where('status', 'used')->get();

        return Inertia::render('Auth/Register', [
            'countries' => $countries,
            'cities'    => $cities,
        ]);
    }

    public function verifyView()
    {
        if (! session('user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/VerifyEmail');
    }

    public function forgotPasswordView()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function resetPasswordView()
    {
        if (! session('user_id')) {
            return redirect()->route('forgot');
        }

        return Inertia::render('Auth/ResetPassword');
    }

    /* ================== AUTH ACTIONS ================== */

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name'   => $data['first_name'],
                'family_name'  => $data['family_name'],
                'phone_number' => $data['phone_number'],
                'email'        => $data['email'],
                'password'     => Hash::make($data['password']),
                'address'      => $data['address'],
            ]);

            UserAddress::create([
                'address_title' => __('flash.main_address_label'),
                'first_name'    => $user->first_name,
                'family_name'   => $user->family_name,
                'phone_number'  => $user->phone_number,
                'user_id'       => $user->id,
                'address'       => $data['address'],
                'country_id'    => $data['country_id'] ?? Country::first()?->id,
                'city_id'       => $data['city_id'] ?? null,
                'main_address'  => true,
            ]);

            return $user;
        });

        // Fire event — queued listener will generate OTP and email it
        event(new UserRegistered($user));

        session(['user_id' => $user->id]);

        return redirect()->route('auth.verify.view');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('web')->attempt($credentials)) {
            $request->session()->regenerate();

            try {
                $this->checkoutServices->mergeGuestCart(Auth::guard('web')->id());
            } catch (\Throwable $e) {
                logger()->error('Cart merge failed on login: ' . $e->getMessage());
            }

            return redirect()->route('home');
        }

        return back()->withErrors(['email' => trans('auth.failed')]);
    }

    public function logout(Request $request)
    {
        $userId = Auth::guard('web')->id();

        if ($userId) {
            $this->checkoutServices->transferCartToGuest($userId);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function verifyCode(Request $request)
    {
        $request->validate(['code' => 'required|digits:4']);

        $userId = session('user_id');

        if (! $userId) {
            return back()->with('error', __('flash.session_expired'));
        }

        $user = User::find($userId);

        if (! $user) {
            return back()->with('error', __('flash.session_expired'));
        }

        $record = User_verfication::where('user_id', $userId)
            ->where('is_reset_password', false)
            ->first();

        if (! $record || $record->code !== $request->code) {
            return back()->with('error', __('flash.invalid_code'));
        }

        if ($record->verification_code_expires_at && now()->isAfter($record->verification_code_expires_at)) {
            return back()->with('error', __('flash.code_expired'));
        }

        $record->update(['is_verified' => true]);
        $user->update(['email_verified_at' => now()]);

        session()->forget('user_id');

        Auth::guard('web')->login($user);

        try {
            $this->checkoutServices->mergeGuestCart($user->id);
        } catch (\Throwable $e) {
            logger()->error('Cart merge failed after email verification: ' . $e->getMessage());
        }

        return redirect()->route('home')->with('success', __('flash.account_activated'));
    }

    public function resendVerificationCode(Request $request)
    {
        $userId = session('user_id');

        if (! $userId) {
            return back()->with('error', __('flash.session_expired'));
        }

        $user = User::find($userId);

        if (! $user) {
            return back()->with('error', __('flash.session_expired'));
        }

        event(new UserRegistered($user));

        return back()->with('success', __('flash.otp_sent'));
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(
            ['email' => 'required|email|exists:users,email'],
            ['email.exists' => __('flash.email_not_found')]
        );

        $user = User::where('email', $request->email)->first();

        // Fire event — queued listener will generate OTP and email it
        event(new UserForgotPassword($user));

        session(['user_id' => $user->id]);

        return back()->with('success', __('flash.otp_sent'));
    }

    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'code'     => 'required|digits:4',
            'password' => 'required|confirmed|min:8',
        ]);

        $userId = session('user_id');

        if (! $userId) {
            return redirect()->route('forgot')->with('error', __('flash.session_expired'));
        }

        $record = User_verfication::where('user_id', $userId)
            ->where('is_reset_password', true)
            ->first();

        if (! $record || $record->code !== $request->code) {
            return back()->with('error', __('flash.invalid_code'));
        }

        if ($record->verification_code_expires_at && now()->isAfter($record->verification_code_expires_at)) {
            return back()->with('error', __('flash.code_expired'));
        }

        $user = User::find($userId);
        $user->update(['password' => Hash::make($request->password)]);
        $record->update(['is_verified' => true]);

        session()->forget('user_id');

        return redirect()->route('login')->with('success', __('flash.password_updated'));
    }
}

