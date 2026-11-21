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
     * request. The owner, the contact data and the location are taken
     * from the authenticated user's account, server-side (see
     * CreateNewAddressRequest).
     */
    return [
      'address_title' => 'required|string|max:255',
      // Same minimum as the registration address (see CreateNewAddressRequest).
      'address' => 'required|string|min:5|max:255',
    ];
    }
}
