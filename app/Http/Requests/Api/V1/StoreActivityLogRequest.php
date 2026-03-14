<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id' => 'required|integer|exists:applications,id',
            'type' => 'required|string|max:50',
            'logged_date' => 'nullable|date',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email',
            'outcome' => 'nullable|string|max:1000',
            'status_from' => 'nullable|string|max:30',
            'status_to' => 'nullable|string|max:30',
        ];
    }
}
