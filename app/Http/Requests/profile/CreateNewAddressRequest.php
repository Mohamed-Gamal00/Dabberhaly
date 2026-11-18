<?php

namespace App\Http\Requests\profile;

use Illuminate\Foundation\Http\FormRequest;

class CreateNewAddressRequest extends FormRequest
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
  /**
   * Only the address itself is validated from the request.
   *
   * The owner (user_id) and the contact data (first_name, family_name,
   * phone_number) are never accepted from the client: they always come
   * from the authenticated user's account, server-side. city/country
   * are optional — the address form does not collect them and the
   * database columns are nullable.
   */
  public function rules(): array
  {
    return [
      'address_title' => 'required|string|max:255',
      'address' => 'required|string|max:255',
      'city_id' => 'nullable|exists:cities,id',
      'country_id' => 'nullable|exists:countries,id',
    ];
  }
}
