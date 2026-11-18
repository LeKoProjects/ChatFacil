<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isPlatformAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'document' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['document' => 'CNPJ/CPF'];
    }
}
