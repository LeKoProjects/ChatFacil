<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class WidgetSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCompanyAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:80'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['required', 'in:left,right'],
            'welcome_message' => ['nullable', 'string', 'max:500'],
            'offline_message' => ['nullable', 'string', 'max:500'],
            'ask_name' => ['boolean'],
            'ask_email' => ['boolean'],
            'ask_phone' => ['boolean'],
            'require_email' => ['boolean'],
            'sound_enabled' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
