<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'linked_application_id' => 'nullable|integer|exists:applications,id',
            'linkable_type' => 'nullable|string|in:application,provider,organization,license,payer,payer_plan',
            'linkable_id' => 'nullable|integer',
            'recurrence' => 'nullable|string|in:none,daily,weekly,monthly',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
