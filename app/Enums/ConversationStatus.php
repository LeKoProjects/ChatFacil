<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Bot = 'bot';
    case Waiting = 'waiting';
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Bot => 'Com o assistente',
            self::Waiting => 'Aguardando atendimento',
            self::Open => 'Em atendimento',
            self::Pending => 'Pendente',
            self::Closed => 'Finalizada',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Closed;
    }
}
