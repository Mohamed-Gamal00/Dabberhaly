<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\User_verfication;
use App\Services\SMSGateways\moraSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class UserService
{
    public function createUser($request)
    {
        $user = null;

        try {
            DB::transaction(function () use ($request, &$user) {
                // Create the user
                $user = User::create([
                    'first_name' => $request->first_name,
                    'family_name' => $request->family_name,
                    'phone_number' => $request->phone_number,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'address' => $request->address
                ]);

                // Create the user's address
                UserAddress::create([
                    'address_title' => 'العنوان الأساسي',
                    'first_name' => $user->first_name,
                    'family_name' => $user->family_name,
                    'phone_number' => $user->phone_number,
                    'user_id' => $user->id,
                    'address' => $request->address,
                    // The mobile app never sends a country — derive it
                    // from the city (a city belongs to one country),
                    // same as the API address create/update. The old
                    // hardcoded 178 (Saudi Arabia) was wrong for every
                    // other country. Falls back to Egypt when no city
                    // was given (the store is Egypt-only).
                    'country_id' => City::find($request->city_id)?->country_id ?? Country::egyptId(),
                    'city_id' => $request->city_id,
                    'main_address' => true
                ]);
            });
        } catch (Exception $e) {
            throw new Exception("Error creating user and address: " . $e->getMessage());
        }

        return $user;
    }

    public function isVerificationCodeExpired($user, $isResetPassword = false)
    {
        $user_verificationCode = User_verfication::where('user_id', $user->id)
            ->where('is_reset_password', $isResetPassword)->first();

        if ($user_verificationCode && $user_verificationCode->verification_code_expires_at) {
            return now()->greaterThan($user_verificationCode->verification_code_expires_at);
        }

        return false;
    }

    public function markUserVerified($user, $code)
    {
        User_verfication::where('user_id', $user->id)
            ->where('code', $code)
            ->update(['is_verified' => true]);
    }

}
