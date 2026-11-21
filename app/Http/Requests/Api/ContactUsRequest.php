<?php

namespace App\Http\Requests\Api;

use App\Helper\ApiResponse;
use App\Models\Admin;
use App\Rules\EgyptianMobileNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ContactUsRequest extends FormRequest
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
            'full_name' => 'required|string|max:255|min:10',
            'email' => 'required|email',
            // Was `numeric|digits_between:7,15` — it rejected every valid
            // +20 number (the known +-rejection bug).
            'phone_number' => ['required', 'string', new EgyptianMobileNumber],
            'message' => 'required|string|min:10'
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
