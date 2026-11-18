<?php

namespace App\Http\Requests\Auth;

use App\Helper\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{Arabic}a-zA-Z\s]+$/u'],
            'family_name'  => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{Arabic}a-zA-Z\s]+$/u'],
            'email'        => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9+\s\-]{7,20}$/', 'unique:users,phone_number'],
            'address'      => ['required', 'string', 'min:5', 'max:255'],
            'country_id'   => ['required', 'integer', 'exists:countries,id'],
            'city_id'      => ['required', 'integer', 'exists:cities,id'],
            'password'     => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'   => __('validation.register.first_name_required'),
            'first_name.min'        => __('validation.register.first_name_min'),
            'first_name.max'        => __('validation.register.first_name_max'),
            'first_name.regex'      => __('validation.register.first_name_regex'),

            'family_name.required'  => __('validation.register.family_name_required'),
            'family_name.min'       => __('validation.register.family_name_min'),
            'family_name.max'       => __('validation.register.family_name_max'),
            'family_name.regex'     => __('validation.register.family_name_regex'),

            'email.required'        => __('validation.register.email_required'),
            'email.email'           => __('validation.register.email_invalid'),
            'email.unique'          => __('validation.register.email_taken'),

            'phone_number.required' => __('validation.register.phone_required'),
            'phone_number.regex'    => __('validation.register.phone_invalid'),
            'phone_number.unique'   => __('validation.register.phone_taken'),

            'address.required'      => __('validation.register.address_required'),
            'address.min'           => __('validation.register.address_min'),

            'country_id.required'   => __('validation.register.country_required'),
            'country_id.exists'     => __('validation.register.country_invalid'),

            'city_id.required'      => __('validation.register.city_required'),
            'city_id.exists'        => __('validation.register.city_invalid'),

            'password.required'     => __('validation.register.password_required'),
            'password.confirmed'    => __('validation.register.password_confirmed'),
            'password.min'          => __('validation.register.password_min'),
            'password.mixed_case'   => __('validation.register.password_mixed_case'),
            'password.numbers'      => __('validation.register.password_numbers'),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->is('api/*')) {
            $response = ApiResponse::sendResponse(422, $validator->errors()->first());
            throw new ValidationException($validator, $response);
        }
    }
}
