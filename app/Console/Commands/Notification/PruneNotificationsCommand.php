<?php

namespace App\Console\Commands\Notification;

use App\Enums\SettingKey;
use App\Services\Notification\NotificationService;
use App\Services\Setting\SettingService;
use Illuminate\Console\Command;

class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune
        {--days= : Override the configured retention window}
        {--dry-run : Report what would be pruned without deleting}';

    protected $description = 'Prune expired and old notifications';

    public function handle(NotificationService $service, SettingService $settings): int
    {
        $configured = $settings->get(SettingKey::NOTIFICATION_RETENTION_DAYS);
        $days = (int) ($this->option('days') ?? $configured ?? config('notification.retention.days', 90));
        $days = max(1, $days);

        if ($this->option('dry-run')) {
            $this->info("Would prune notifications older than {$days} days.");

            return self::SUCCESS;
        }

        $count = $service->pruneExpired($days);

        $this->info("Pruned {$count} notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
