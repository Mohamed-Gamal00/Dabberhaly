<?php

namespace App\Http\Requests\Dashboard\Client;

use App\Rules\EgyptianMobileNumber;
use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
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
    $rules = [
      'first_name' => 'required|string|max:255',
      'family_name' => 'required|string|max:255',
      'email' => 'required|email|max:255|unique:users,email,' . $this->route('client'),
      // A client's phone is a person's Egyptian mobile (same rule as
      // Auth/RegisterRequest).
      'phone_number' => ['required', new EgyptianMobileNumber],
    ];

    return $rules;
  }
}
