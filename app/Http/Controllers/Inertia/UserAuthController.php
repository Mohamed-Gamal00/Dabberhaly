<?php

namespace App\Http\Controllers\Inertia;

use App\Events\UserForgotPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Models\User_verfication;
use App\Models\UserAddress;
use App\Notifications\EmailVerificationCodeNotification;
use App\Services\CheckOut\CheckoutServices;
use App\Services\VerificationServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

class UserAuthController extends Controller
{
    protected CheckoutServices $checkoutServices;
    protected VerificationServices $verificationServices;

    public function __construct(CheckoutServices $checkoutServices, VerificationServices $verificationServices)
    {
        $this->checkoutServices = $checkoutServices;
        $this->verificationServices = $verificationServices;
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
        if (! session('pending_registration')) {
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

        // Store registration data in session — no DB write until email is verified
        session([
            'pending_registration' => [
                'first_name'   => $data['first_name'],
                'family_name'  => $data['family_name'],
                'phone_number' => $data['phone_number'],
                'email'        => $data['email'],
                'password'     => Hash::make($data['password']),
                'address'      => $data['address'],
                'country_id'   => $data['country_id'] ?? null,
                'city_id'      => $data['city_id'] ?? null,
            ],
        ]);

        $this->sendRegistrationOtp($data['email'], $data['first_name']);

        return redirect()->route('auth.verify.view');
    }

    public function login(LoginRequest $request)
    {
        // LoginRequest::authenticate() enforces the 5-attempt RateLimiter
        // (throttle key: email + IP) and throws a validation error when
        // the limit is hit or the credentials are wrong.
        $request->authenticate();

        $request->session()->regenerate();

        try {
            $this->checkoutServices->mergeGuestCart(Auth::guard('web')->id());
        } catch (\Throwable $e) {
            logger()->error('Cart merge failed on login: ' . $e->getMessage());
        }

        return redirect()->route('home');
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

        $pending = session('pending_registration');

        if (! $pending) {
            return back()->with('error', __('flash.session_expired'));
        }

        $otpData = session('pending_otp');

        if (! $otpData || $otpData['code'] !== $request->code) {
            // Wrong-code attempts invalidate the code after the limit,
            // like the API and web-reset paths — a 4-digit code must
            // not be brute-forceable for its whole 10-minute life.
            $attempts = (int) session('pending_otp_attempts', 0) + 1;

            if ($attempts >= VerificationServices::MAX_OTP_ATTEMPTS) {
                session()->forget(['pending_otp', 'pending_otp_attempts']);

                return back()->with('error', __('flash.code_expired'));
            }

            session(['pending_otp_attempts' => $attempts]);

            return back()->with('error', __('flash.invalid_code'));
        }

        if (now()->isAfter($otpData['expires_at'])) {
            return back()->with('error', __('flash.code_expired'));
        }

        // OTP valid — now create the user and address
        $user = DB::transaction(function () use ($pending) {
            $user = User::create([
                'first_name'        => $pending['first_name'],
                'family_name'       => $pending['family_name'],
                'phone_number'      => $pending['phone_number'],
                'email'             => $pending['email'],
                'password'          => $pending['password'],
                'address'           => $pending['address'],
                'email_verified_at' => now(),
            ]);

            UserAddress::create([
                'address_title' => __('flash.main_address_label'),
                'first_name'    => $user->first_name,
                'family_name'   => $user->family_name,
                'phone_number'  => $user->phone_number,
                'user_id'       => $user->id,
                'address'       => $pending['address'],
                'country_id'    => $pending['country_id'] ?? Country::first()?->id,
                'city_id'       => $pending['city_id'] ?? null,
                'main_address'  => true,
            ]);

            return $user;
        });

        session()->forget(['pending_registration', 'pending_otp']);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        try {
            $this->checkoutServices->mergeGuestCart($user->id);
        } catch (\Throwable $e) {
            logger()->error('Cart merge failed after email verification: ' . $e->getMessage());
        }

        return redirect()->route('home')->with('success', __('flash.account_activated'));
    }

    public function resendVerificationCode(Request $request)
    {
        $pending = session('pending_registration');

        if (! $pending) {
            return back()->with('error', __('flash.session_expired'));
        }

        $this->sendRegistrationOtp($pending['email'], $pending['first_name']);

        return back()->with('success', __('flash.otp_sent'));
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(
            ['email' => 'required|email|exists:users,email'],
            ['email.exists' => __('flash.email_not_found')]
        );

        $key = 'forgot-password:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);
            return back()->with('error', __('flash.too_many_attempts', ['minutes' => $minutes]));
        }

        RateLimiter::hit($key, 600); // 10-minute decay window

        $user = User::where('email', $request->email)->first();

        // Create the reset code HERE, synchronously: the queued listener
        // only sends it. If creation lived in the listener, no queue
        // worker would mean no code is ever created and reset is dead.
        $code = $this->verificationServices->createPasswordResetCode($user->id);

        event(new UserForgotPassword($user, $code));

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
            // Wrong-code attempts invalidate the code after the limit, so
            // a 4-digit code cannot be brute-forced indefinitely.
            $attempts = $this->verificationServices->recordFailedOtpAttempt($userId, true);

            if ($attempts >= VerificationServices::MAX_OTP_ATTEMPTS) {
                return back()->with('error', __('flash.code_expired'));
            }

            return back()->with('error', __('flash.invalid_code'));
        }

        if ($record->verification_code_expires_at && now()->isAfter($record->verification_code_expires_at)) {
            return back()->with('error', __('flash.code_expired'));
        }

        $user = User::find($userId);
        $user->update(['password' => Hash::make($request->password)]);
        $record->update(['is_verified' => true]);
        $this->verificationServices->clearOtpAttempts($userId, true);

        session()->forget('user_id');

        return redirect()->route('login')->with('success', __('flash.password_updated'));
    }

    private function sendRegistrationOtp(string $email, string $name): void
    {
        $code = str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        session([
            'pending_otp' => [
                'code'       => $code,
                'expires_at' => now()->addMinutes(10),
            ],
        ]);

        // A fresh code resets the wrong-attempt counter.
        session()->forget('pending_otp_attempts');

        Notification::route('mail', $email)
            ->notify(new EmailVerificationCodeNotification($code, $name));
    }
}

