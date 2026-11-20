<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('branches', 'code')->ignore($this->route('branch'))],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'campaign_ids' => ['array'],
            'campaign_ids.*' => ['integer', 'exists:campaigns,id'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Use letters, numbers, dashes or underscores only.'];
    }
}
