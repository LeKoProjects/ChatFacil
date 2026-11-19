<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração do chatbot por regras da empresa (uma por empresa).
 *
 * @property int $id
 * @property int $company_id
 * @property bool $is_enabled
 * @property string $greeting
 * @property string $fallback_message
 * @property string $handoff_message
 * @property string $offline_handoff_message
 * @property string $resolved_message
 */
class Chatbot extends Model
{
    use BelongsToCompany;

    public const DEFAULTS = [
        'is_enabled' => false,
        'greeting' => 'Olá! Sou o assistente virtual. Escolha uma opção abaixo ou digite sua dúvida.',
        'fallback_message' => 'Não encontrei uma resposta para isso. Escolha uma opção do menu ou fale com um atendente.',
        'handoff_message' => 'Certo! Vou transferir você para um atendente. Aguarde só um instante.',
        'offline_handoff_message' => 'Nossa equipe não está disponível agora, mas sua mensagem ficou registrada e responderemos assim que possível.',
        'resolved_message' => 'Que bom que ajudei! Se precisar de algo mais, é só chamar.',
    ];

    protected $fillable = [
        'company_id', 'is_enabled', 'greeting', 'fallback_message',
        'handoff_message', 'offline_handoff_message', 'resolved_message',
    ];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /** Configuração da empresa atual (com os textos padrão se ainda não foi salva). */
    public static function current(): self
    {
        return self::query()->first() ?? new self(self::DEFAULTS);
    }
}
