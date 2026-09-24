<?php

namespace App\Enums;

/**
 * The visual/behavioural priority of an in-app notification.
 */
enum NotificationPriority: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Info',
            self::SUCCESS => 'Success',
            self::WARNING => 'Warning',
            self::CRITICAL => 'Critical',
        };
    }

    /**
     * Whether the priority should be dispatched on the critical queue.
     */
    public function isCritical(): bool
    {
        return $this === self::CRITICAL;
    }
}
