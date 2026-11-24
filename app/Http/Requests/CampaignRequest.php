<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->code))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('campaigns', 'code')->ignore($this->route('campaign'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'public_show_statuses' => ['boolean'],
            'admin_ids' => ['array'],
            'admin_ids.*' => ['integer', 'exists:users,id'],
            'manager_ids' => ['array'],
            'manager_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Use letters, numbers, dashes or underscores only.'];
    }
}
