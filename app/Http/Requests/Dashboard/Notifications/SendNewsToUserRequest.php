<?php

namespace App\Http\Requests\Dashboard\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNewsToUserRequest extends FormRequest
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
    return [
      'title'       => 'required|string|max:255',
      'body'        => 'required|string',
      // Send to every subscriber: no user list needs to be posted.
      'send_to_all' => 'nullable|boolean',
      'users'       => [Rule::requiredIf(fn () => ! $this->boolean('send_to_all')), 'array', 'min:1'],
      'users.*'     => 'required|integer|exists:send_news_to_users,id',
    ];
  }
}
