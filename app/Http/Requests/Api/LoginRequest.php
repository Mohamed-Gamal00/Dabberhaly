<?php

namespace App\Http\Requests\Api;

use App\Helper\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use App\Rules\EgyptianMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            // Same Egyptian-mobile rule as registration: a registered
            // user must be able to log in with the number they
            // registered with, in local (010...) or +20 form.
            'phone_number' => ['required', new EgyptianMobileNumber, 'exists:users,phone_number'],
            'password' => ['required']
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
