<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Envio de mensagem (widget e central). Autorização feita no controller/policy. */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.config('nexo.widget.max_message_length')],
            'client_message_id' => ['required', 'uuid'],
        ];
    }
}
