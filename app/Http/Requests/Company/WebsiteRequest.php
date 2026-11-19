<?php

namespace App\Http\Requests\Company;

use App\Support\Widget\DomainAuthorizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class WebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCompanyAdmin();
    }

    protected function prepareForValidation(): void
    {
        $domains = $this->input('domains', []);

        if (is_string($domains)) {
            $domains = preg_split('/[\s,;]+/', $domains) ?: [];
        }

        $this->merge(['domains' => array_values(array_filter(array_map('trim', (array) $domains)))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:255', $this->validHost()],
            'domains' => ['array', 'max:20'],
            'domains.*' => ['string', 'max:255', $this->validHost()],
        ];
    }

    private function validHost(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || DomainAuthorizer::normalize($value) === null) {
                $fail('Domínio inválido: '.(is_string($value) ? $value : ''));
            }
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nome', 'url' => 'endereço do site', 'domains' => 'domínios'];
    }
}
