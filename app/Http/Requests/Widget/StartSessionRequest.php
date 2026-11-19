<?php

namespace App\Http\Requests\Widget;

use Illuminate\Foundation\Http\FormRequest;

class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
