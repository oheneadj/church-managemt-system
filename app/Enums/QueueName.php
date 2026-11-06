<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The six named queues, highest priority first (D-025, docs/06). A job always names its queue so a slow job can never
 * delay a sign-in code.
 */
enum QueueName: string
{
    case Critical = 'critical';
    case Payments = 'payments';
    case Notifications = 'notifications';
    case Reports = 'reports';
    case Exports = 'exports';
    case Scheduled = 'scheduled';

    /**
     * Human-readable text for logs and screens.
     */
    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Sign-in codes',
            self::Payments => 'Payments',
            self::Notifications => 'Messages',
            self::Reports => 'Reports',
            self::Exports => 'Exports',
            self::Scheduled => 'Scheduled work',
        };
    }
}
