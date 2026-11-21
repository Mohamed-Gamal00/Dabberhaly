<?php

namespace App\Http\Requests\Api;

use App\Rules\EgyptianMobileNumber;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        $isAddingNewAddress = !$this->has('user_address');

        return [

            'user_address' => $isAddingNewAddress ? 'nullable' : 'required', // لو اليوزر مسجل وهيضيف عنوان جديد
            'terms' => 'nullable|boolean',
            // Only the payment methods the admin enabled in settings:
            // a disabled method must never be orderable, and an unknown
            // value must never silently become cash on delivery (the
            // checkout treats anything but card_payment as COD).
            'payment_method' => ['required', Rule::in(Setting::allowedPaymentMethodValues())],
            'first_name' => $isAddingNewAddress ? ['required', 'string', 'max:255'] : 'nullable',
            'last_name' => $isAddingNewAddress ? ['required', 'string', 'max:255'] : 'nullable',
            'phone_number' => $isAddingNewAddress ? ['required', new EgyptianMobileNumber] : 'nullable',
            'country_id' => ['nullable', 'exists:countries,id'],
            'city_id' => $isAddingNewAddress ? ['required', 'exists:cities,id'] : 'nullable',
            'address' => $isAddingNewAddress ? ['required', 'string', 'max:255'] : 'nullable',
        ];
    }

    /**
     * Custom error messages for validation.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'user_address.required' => 'Please select or add a new address',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Payment method is not supported',
            'first_name.required' => 'First name is required for the new address',
            'last_name.required' => 'Last name is required for the new address',
            'phone_number.required' => 'Phone number is required for the new address',
            'city_id.required' => 'City is required for the new address',
            'address.required' => 'Address is required for the new address',
        ];
    }
}
