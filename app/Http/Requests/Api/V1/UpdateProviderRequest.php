<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'sometimes|nullable|integer|exists:organizations,id',
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'credentials' => 'sometimes|nullable|string|max:50',
            'npi' => 'sometimes|nullable|string|size:10',
            'taxonomy' => 'sometimes|nullable|string|max:20',
            'specialty' => 'sometimes|nullable|string|max:100',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string|max:20',
            'caqh_id' => 'sometimes|nullable|string|max:20',
        ];
    }
}
