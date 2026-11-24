<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $scoped = $this->input('role') !== Role::SuperAdmin->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'mobile' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'password' => [$target ? 'nullable' : 'required', 'string', Password::defaults(), 'max:255'],
            'role' => ['required', new Enum(Role::class)],
            'campaign_ids' => [Rule::requiredIf($scoped), 'array', $scoped ? 'min:1' : 'min:0'],
            'campaign_ids.*' => ['integer', 'exists:campaigns,id'],
            'permissions' => ['array'],
            'permissions.*' => [new Enum(Permission::class)],
            'lead_visibility' => ['nullable', Rule::in([User::VISIBILITY_CAMPAIGN, User::VISIBILITY_ASSIGNED])],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'campaign_ids.required' => 'Select at least one campaign.',
            'campaign_ids.min' => 'Select at least one campaign.',
        ];
    }
}
