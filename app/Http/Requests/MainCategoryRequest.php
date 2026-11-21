<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MainCategoryRequest extends FormRequest
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
      'name' => ['required', 'string', 'max:255'],
      'name_en' => ['nullable', 'string', 'max:255'],
      'description' => ['nullable', 'string'],
      'description_en' => ['nullable', 'string'],
      // The form sends choice_id[] (an array); the old scalar
      // `exists` rule did not validate the individual ids, so a
      // bogus id reached the FK-constrained category_choices
      // pivot and blew up with a 500 instead of a 422.
      'choice_id' => ['nullable', 'array'],
      'choice_id.*' => ['exists:choices,id'],
      'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],

    ];
  }
}
