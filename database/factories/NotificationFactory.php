<?php

namespace Database\Factories;

use App\Enums\NotificationPriority;
use App\Enums\NotificationTargetType;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Notification>
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => NotificationType::SYSTEM_ANNOUNCEMENT->value,
            'priority' => NotificationPriority::INFO,
            'title' => fake()->sentence(4),
            'body' => fake()->optional()->paragraph(),
            'action_url' => null,
            'data' => null,
            'group_key' => null,
            'created_by' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Address the notification to every user.
     */
    public function all(): static
    {
        return $this->afterCreating(fn (Notification $notification): mixed => $notification->targets()->create([
            'target_type' => NotificationTargetType::ALL,
            'target_id' => null,
        ]));
    }

    /**
     * Address the notification to a specific user.
     */
    public function forUser(User|int $user): static
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        return $this->afterCreating(fn (Notification $notification): mixed => $notification->targets()->create([
            'target_type' => NotificationTargetType::USER,
            'target_id' => $id,
        ]));
    }

    /**
     * Address the notification to a role.
     */
    public function forRole(int|string $roleId): static
    {
        return $this->afterCreating(fn (Notification $notification): mixed => $notification->targets()->create([
            'target_type' => NotificationTargetType::ROLE,
            'target_id' => $roleId,
        ]));
    }

    /**
     * Mark the notification as critical.
     */
    public function critical(): static
    {
        return $this->state(fn (): array => [
            'priority' => NotificationPriority::CRITICAL,
        ]);
    }

    /**
     * Mark the notification as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Set the notification type.
     */
    public function ofType(NotificationType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type->value,
            'priority' => $type->defaultPriority(),
        ]);
    }
}
