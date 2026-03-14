<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id' => 'required|integer|exists:providers,id',
            'organization_id' => 'nullable|integer|exists:organizations,id',
            'payer_id' => 'required|integer|exists:payers,id',
            'payer_plan_id' => 'nullable|integer|exists:payer_plans,id',
            'state' => 'required|string|size:2',
            'type' => 'nullable|string|in:initial,revalidation,addition',
            'wave' => 'nullable|integer|min:1|max:10',
            'status' => 'nullable|string|max:30',
            'portal_url' => 'nullable|url|max:500',
            'application_ref' => 'nullable|string|max:100',
            'enrollment_id' => 'nullable|string|max:100',
            'submitted_date' => 'nullable|date',
            'effective_date' => 'nullable|date',
            'est_monthly_revenue' => 'nullable|numeric|min:0',
            'payer_contact_name' => 'nullable|string|max:100',
            'payer_contact_phone' => 'nullable|string|max:20',
            'payer_contact_email' => 'nullable|email',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
        ];
    }
}
