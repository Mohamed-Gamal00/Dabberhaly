<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Http\Requests\profile\CreateNewAddressRequest;
use App\Http\Requests\profile\UpdateNewAddressRequest;
use App\Http\Requests\profile\UserRequest;
use App\Models\City;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\User_verfication;
use App\Services\SMSGateways\moraSms;
use App\Services\VerificationServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserProfileController extends Controller
{
    protected $sms_service;
    protected $moraSms;

    public function __construct(VerificationServices $services, moraSms $moraSmsGateway)
    {
        $this->sms_service = $services;
        $this->moraSms = $moraSmsGateway;
    }

    // public function index()
    // {
    //     $user = Auth::user()->load('orders.orderItems');
    //     // return $user;
    //     return Inertia::render('Profile/Index', compact('user'));
    // }

    public function index()
    {
        $user = Auth::user();

        $user->load([
            'orders.orderItems',
            'addresses',
            'wishlistProducts' => function ($query) use ($user) {
                $query->with(['images', 'parent'])
                    ->withIsInWishlist($user);
            },
        ]);
        return Inertia::render('Profile/Index', compact('user'));
    }

    public function userAddressesCreate()
    {
        $user = Auth::user();
        $countryId = $user->addresses()->where('main_address', 1)->value('country_id');
        $cities = City::where('country_id', $countryId)->where('status', 'used')->get();
        return Inertia::render('Profile/Addresses/Create', compact('cities'));
    }

    public function userAddressesStore(CreateNewAddressRequest $request)
    {
        $user = Auth::user();

        // The address belongs to the logged-in user and carries his
        // account data — none of that is accepted from the client.
        UserAddress::create(array_merge($request->validated(), [
            'user_id' => $user->id,
        ], $this->addressAccountData($user)));

        return back()->with('success', __('flash.address_added'));
    }

    public function userAddressesEdit($addressId)
    {
        $address = UserAddress::findOrFail($addressId);
        $cities = City::where('country_id', $address->country_id)->where('status', 'used')->get();
        return Inertia::render('Profile/Addresses/Edit', compact('address', 'cities'));
    }

    public function userAddressesUpdate(UpdateNewAddressRequest $request, $addressId)
    {
        $user = Auth::user();
        $address = $user->addresses()->findOrFail($addressId);

        // The contact and location data always mirror the account, even on edit.
        $address->update(array_merge($request->validated(), $this->addressAccountData($user)));

        return back()->with('success', __('flash.address_updated'));
    }

    public function userAddressesDestroy($addressId)
    {
        $address = Auth::user()->addresses()->findOrFail($addressId);

        // The main address feeds the customer's data into orders,
        // so it can never be deleted (same rule as the mobile API).
        if ($address->main_address) {
            return back()->with('danger', __('flash.cannot_delete_main_address'));
        }

        $address->delete();

        return back()->with('danger', __('flash.address_deleted'));
    }

    public function setMainAddress($addressId)
    {
        $user = Auth::user();
        $user->addresses()->update(['main_address' => 0]);
        $user->addresses()->where('id', $addressId)->update(['main_address' => 1]);
        return back()->with('success', __('flash.main_address_updated'));
    }

    /**
     * The data every address takes from the customer's account: the
     * name and phone from the user record, the country/city from his
     * main address. The users table has no city/country columns —
     * registration stores them on the main address, so that is the
     * source of truth. Nulls are dropped so an update never wipes an
     * address's existing values when the account has none.
     */
    private function addressAccountData(User $user): array
    {
        $main = $user->addresses()->where('main_address', true)->first();

        return array_filter([
            'first_name' => $user->first_name,
            'family_name' => $user->family_name,
            'phone_number' => $user->phone_number,
            'country_id' => $main?->country_id,
            'city_id' => $main?->city_id,
        ], fn ($value) => $value !== null);
    }

    public function changePasswordView()
    {
        return Inertia::render('Profile/ChangePassword');
    }

    public function updatePassword(Request $request)
    {
        $request->validate(['new_password' => 'required|confirmed|min:6']);
        $user = Auth::user();
        $user->update(['password' => Hash::make($request->new_password)]);
        return to_route('user.info')->with('info', __('flash.password_changed'));
    }

    public function updateUserInfo(UserRequest $request)
    {
        $user = Auth::user();
        $user->update($request->validated());

        return back()->with('success', __('flash.profile_updated'));
    }
}
