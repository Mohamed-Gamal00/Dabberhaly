<?php

namespace App\Http\Requests\Dashboard\Client;

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
      // Canonical phone rule (same as Auth/RegisterRequest): digits,
      // spaces, dashes and a leading + — `numeric` rejected + phones.
      'phone_number' => ['required', 'regex:/^[0-9+\s\-]{7,20}$/'],
    ];

    return $rules;
  }
}
