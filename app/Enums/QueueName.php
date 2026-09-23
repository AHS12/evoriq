<?php

namespace App\Enums;

enum QueueName: string
{
    case CRITICAL = 'critical';
    case DEFAULT = 'default';
    case HEAVY = 'heavy';
}
