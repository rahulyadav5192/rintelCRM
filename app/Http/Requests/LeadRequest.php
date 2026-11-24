<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Field-level validation for creating/updating leads. Relationship validity
 * (owner ↔ campaign ↔ manager ↔ statuses) and per-field permissions are enforced by LeadService.
 * Note there is intentionally no lead_number rule: a submitted lead ID is never read.
 */
class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization happens in the controller via LeadPolicy.
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => $this->mobile ? preg_replace('/[\s\-()]/', '', (string) $this->mobile) : $this->mobile,
            'email' => $this->email ? strtolower(trim((string) $this->email)) : null,
            'post_code' => $this->post_code ? strtoupper(trim((string) $this->post_code)) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'lead_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'title' => ['nullable', Rule::in(Lead::TITLES)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'post_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9 ]{2,10}$/'],
            'admin_id' => ['nullable', 'integer'],
            'campaign_id' => ['required', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'lead_status_id' => ['nullable', 'integer'],
            'payment_status_id' => ['nullable', 'integer'],
            'follow_up_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid mobile number (7–15 digits, optional leading +).',
            'post_code.regex' => 'Enter a valid post code.',
            'campaign_id.required' => 'Select a campaign.',
        ];
    }
}
