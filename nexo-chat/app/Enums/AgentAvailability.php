<?php

namespace App\Enums;

enum AgentAvailability: string
{
    case Online = 'online';
    case Busy = 'busy';
    case Away = 'away';
    case Offline = 'offline';

    public function acceptsChats(): bool
    {
        return $this === self::Online;
    }
}
