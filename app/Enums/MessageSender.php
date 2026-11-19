<?php

namespace App\Enums;

enum MessageSender: string
{
    case Visitor = 'visitor';
    case Agent = 'agent';
    case System = 'system';
}
