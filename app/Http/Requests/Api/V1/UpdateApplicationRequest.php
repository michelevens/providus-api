<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id' => 'sometimes|integer|exists:providers,id',
            'organization_id' => 'sometimes|nullable|integer|exists:organizations,id',
            'payer_id' => 'sometimes|integer|exists:payers,id',
            'payer_plan_id' => 'sometimes|nullable|integer|exists:payer_plans,id',
            'state' => 'sometimes|string|size:2',
            'type' => 'sometimes|nullable|string|in:initial,revalidation,addition',
            'wave' => 'sometimes|nullable|integer|min:1|max:10',
            'status' => 'sometimes|nullable|string|max:30',
            'portal_url' => 'sometimes|nullable|url|max:500',
            'application_ref' => 'sometimes|nullable|string|max:100',
            'enrollment_id' => 'sometimes|nullable|string|max:100',
            'submitted_date' => 'sometimes|nullable|date',
            'effective_date' => 'sometimes|nullable|date',
            'est_monthly_revenue' => 'sometimes|nullable|numeric|min:0',
            'payer_contact_name' => 'sometimes|nullable|string|max:100',
            'payer_contact_phone' => 'sometimes|nullable|string|max:20',
            'payer_contact_email' => 'sometimes|nullable|email',
            'notes' => 'sometimes|nullable|string|max:2000',
            'tags' => 'sometimes|nullable|array',
        ];
    }
}
