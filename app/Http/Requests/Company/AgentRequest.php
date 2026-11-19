<?php

namespace App\Http\Requests\Company;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCompanyAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User|null $agent */
        $agent = $this->route('agent');
        $creating = $agent === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($agent?->id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::defaults()],
            'role' => ['required', Rule::in([UserRole::Agent->value, UserRole::CompanyAdmin->value])],
            'max_concurrent_chats' => ['required', 'integer', 'min:1', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
