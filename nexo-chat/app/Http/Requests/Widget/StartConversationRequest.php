<?php

namespace App\Http\Requests\Widget;

use App\Models\Visitor;
use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('visitor') instanceof Visitor;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Visitor $visitor */
        $visitor = $this->user('visitor');
        $form = $visitor->currentSession?->widget;

        return [
            'name' => [$form?->ask_name ? 'required' : 'nullable', 'string', 'max:120'],
            'email' => [$form?->require_email ? 'required' : 'nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:'.config('nexo.widget.max_message_length')],
            'client_message_id' => ['nullable', 'uuid'],
        ];
    }
}
