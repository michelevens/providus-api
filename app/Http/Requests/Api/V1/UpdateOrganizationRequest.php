<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'npi' => 'sometimes|nullable|string|size:10',
            'tax_id' => 'sometimes|nullable|string|max:20',
            'address_street' => 'sometimes|nullable|string|max:255',
            'address_city' => 'sometimes|nullable|string|max:100',
            'address_state' => 'sometimes|nullable|string|size:2',
            'address_zip' => 'sometimes|nullable|string|max:10',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email',
            'taxonomy' => 'sometimes|nullable|string|max:20',
        ];
    }
}
