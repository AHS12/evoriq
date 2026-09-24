<?php

namespace App\Console\Commands\Notification;

use App\DTOs\Notification\NotificationDTO;
use App\DTOs\Notification\NotificationTargetDTO;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTargetType;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

/**
 * Development helper to exercise the notification pipeline without a producer
 * feature. Never exposed over HTTP.
 */
class SendTestNotificationCommand extends Command
{
    protected $signature = 'notifications:send
        {--title=Test notification : The notification title}
        {--body= : Optional body text}
        {--type=system.announcement : The notification type}
        {--priority= : info|success|warning|critical}
        {--user=* : Target user ids}
        {--role=* : Target role ids}
        {--all : Broadcast to every user}';

    protected $description = 'Create a test notification (development helper)';

    public function handle(NotificationService $service): int
    {
        $targets = $this->resolveTargets();

        $priorityOption = $this->option('priority');

        $title = trim((string) $this->option('title'));

        $notification = $service->create(new NotificationDTO(
            type: (string) $this->option('type'),
            title: $title !== '' ? $title : 'Test notification',
            body: $this->option('body') !== null ? (string) $this->option('body') : null,
            priority: is_string($priorityOption) && $priorityOption !== ''
                ? NotificationPriority::tryFrom($priorityOption)
                : null,
            targets: $targets,
        ));

        if ($notification === null) {
            $this->warn('Notifications are disabled (NOTIFICATION_ENABLED is off).');

            return self::SUCCESS;
        }

        $this->info("Notification #{$notification->getKey()} created.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, NotificationTargetDTO>
     */
    private function resolveTargets(): array
    {
        if ($this->option('all')) {
            return [new NotificationTargetDTO(NotificationTargetType::ALL)];
        }

        $targets = [];

        foreach ((array) $this->option('user') as $userId) {
            $targets[] = new NotificationTargetDTO(NotificationTargetType::USER, (int) $userId);
        }

        foreach ((array) $this->option('role') as $roleId) {
            $targets[] = new NotificationTargetDTO(NotificationTargetType::ROLE, (int) $roleId);
        }

        if ($targets === []) {
            $this->info('No target provided; defaulting to everyone.');

            return [new NotificationTargetDTO(NotificationTargetType::ALL)];
        }

        return $targets;
    }
}
