<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id' => 'required|integer|exists:applications,id',
            'type' => 'required|string|in:status_check,document_collection,renewal_check,general,escalation',
            'due_date' => 'required|date',
            'method' => 'nullable|string|in:phone,email,portal,fax',
            'contact_name' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email',
            'outcome' => 'nullable|string|max:500',
            'next_action' => 'nullable|string|max:500',
        ];
    }
}
