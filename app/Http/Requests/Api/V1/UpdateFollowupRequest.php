<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id' => 'sometimes|integer|exists:applications,id',
            'type' => 'sometimes|string|in:status_check,document_collection,renewal_check,general,escalation',
            'due_date' => 'sometimes|date',
            'method' => 'sometimes|nullable|string|in:phone,email,portal,fax',
            'contact_name' => 'sometimes|nullable|string|max:100',
            'contact_phone' => 'sometimes|nullable|string|max:20',
            'contact_email' => 'sometimes|nullable|email',
            'outcome' => 'sometimes|nullable|string|max:500',
            'next_action' => 'sometimes|nullable|string|max:500',
        ];
    }
}
