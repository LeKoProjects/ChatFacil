<?php

namespace App\Http\Requests\Widget;

use Illuminate\Foundation\Http\FormRequest;

class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A página de origem vem do navegador do visitante e aparece como link na
     * central: só aceita http(s). Outro valor é descartado (não quebra o widget).
     */
    protected function prepareForValidation(): void
    {
        $page = $this->input('page_url');

        if ($page !== null && (! is_string($page) || ! preg_match('#^https?://[^\s]+$#i', $page))) {
            $this->merge(['page_url' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2000'],
            'token' => ['nullable', 'string', 'size:64'],
        ];
    }
}
