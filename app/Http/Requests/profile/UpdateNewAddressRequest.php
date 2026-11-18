<?php

namespace App\Http\Requests\profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNewAddressRequest extends FormRequest
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
    /**
     * Same rules as creating: only the address itself comes from the
     * request. The owner and the contact data are taken from the
     * authenticated user's account, server-side (see
     * CreateNewAddressRequest).
     */
    return [
      'address_title' => 'required|string|max:255',
      'address' => 'required|string|max:255',
      'city_id' => 'nullable|exists:cities,id',
      'country_id' => 'nullable|exists:countries,id',
    ];
    }
}
