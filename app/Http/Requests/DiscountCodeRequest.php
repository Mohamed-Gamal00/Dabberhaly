<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscountCodeRequest extends FormRequest
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
    $discountCodeId = $this->route('discount_code');
    $rules = [
      'name' => 'required|string|max:255',
      'code' => 'required|string|max:255|unique:discount_codes,code,' . $discountCodeId,
      'price' => ['required', 'numeric', 'min:0'],
      'status' => 'required|in:active,inactive',
      // A usage count is a whole number, never 1.5 or negative.
      'number_of_used' => 'required|integer|min:0',
      'product_ids' => 'nullable|array',
      'product_ids.*' => 'nullable|numeric|exists:products,id',
      'discount_type' => 'required|in:percentage,price'
    ];

    // A percentage over 100 would discount every eligible item to
    // zero (CheckDiscountService clamps at 0), so it is not a valid
    // percentage. A fixed-amount discount has no such ceiling.
    if ($this->input('discount_type') === 'percentage') {
      $rules['price'][] = 'max:100';
    }

    return $rules;
  }
}
