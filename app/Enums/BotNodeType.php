<?php

namespace App\Enums;

/** Tipo de uma opção do menu do chatbot. */
enum BotNodeType: string
{
    /** Abre um submenu com as opções filhas. */
    case Menu = 'menu';
    /** Mostra uma resposta pronta. */
    case Answer = 'answer';
    /** Transfere para um atendente humano. */
    case Handoff = 'handoff';

    public function label(): string
    {
        return match ($this) {
            self::Menu => 'Submenu',
            self::Answer => 'Resposta pronta',
            self::Handoff => 'Transferir para atendente',
        };
    }
}
