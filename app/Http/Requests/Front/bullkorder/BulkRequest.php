<?php

namespace App\Http\Requests\Front\bullkorder;

use App\Rules\EgyptianMobileNumber;
use Illuminate\Foundation\Http\FormRequest;

// This class was missing entirely (the API bulk-order endpoint
// type-hinted it but the file never existed), so every call to
// POST /api/v1/store-bulk-order 500'd before validation could run.
// Rules mirror the web bulk-order form (Inertia\BulkOrderController).
class BulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', new EgyptianMobileNumber, 'max:255'],
            'company_name' => 'nullable|string|max:255',
            'description' => 'required|string',
        ];
    }
}
