<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id' => 'required|integer|exists:providers,id',
            'state' => 'required|string|size:2',
            'license_number' => 'required|string|max:50',
            'license_type' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,expired,pending,suspended',
            'issue_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
            'renewal_date' => 'nullable|date',
            'compact_state' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
