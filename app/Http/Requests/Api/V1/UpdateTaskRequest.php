<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|nullable|string|max:50',
            'priority' => 'sometimes|nullable|string|in:low,medium,high,urgent',
            'due_date' => 'sometimes|nullable|date',
            'linked_application_id' => 'sometimes|nullable|integer|exists:applications,id',
            'linkable_type' => 'sometimes|nullable|string|in:application,provider,organization,license,payer,payer_plan',
            'linkable_id' => 'sometimes|nullable|integer',
            'recurrence' => 'sometimes|nullable|string|in:none,daily,weekly,monthly',
            'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
