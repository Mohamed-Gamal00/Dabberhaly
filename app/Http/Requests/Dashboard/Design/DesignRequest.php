<?php

namespace App\Http\Requests\Dashboard\Design;

use Illuminate\Foundation\Http\FormRequest;

class DesignRequest extends FormRequest
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
      'title' => 'nullable|string|max:255',
      'description' => 'nullable|string',
    ];

    if ($this->isMethod('post')) { // For store method
      $rules['image'] = ['required', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'];
    }

    if ($this->isMethod('put') || $this->isMethod('patch')) { // For update method
      $rules['image'] = ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'];
    }

    return $rules;
  }

}
