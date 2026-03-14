<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id' => 'sometimes|integer|exists:providers,id',
            'state' => 'sometimes|string|size:2',
            'license_number' => 'sometimes|string|max:50',
            'license_type' => 'sometimes|nullable|string|max:50',
            'status' => 'sometimes|nullable|string|in:active,expired,pending,suspended',
            'issue_date' => 'sometimes|nullable|date',
            'expiration_date' => 'sometimes|nullable|date',
            'renewal_date' => 'sometimes|nullable|date',
            'compact_state' => 'sometimes|nullable|boolean',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
