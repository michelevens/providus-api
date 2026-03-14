<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'credentials' => 'nullable|string|max:50',
            'npi' => 'nullable|string|size:10',
            'taxonomy' => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'caqh_id' => 'nullable|string|max:20',
        ];
    }
}
