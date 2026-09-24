<?php

namespace Database\Factories;

use App\Enums\NotificationTargetType;
use App\Models\Notification;
use App\Models\NotificationTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTarget>
 */
class NotificationTargetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<NotificationTarget>
     */
    protected $model = NotificationTarget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'target_type' => NotificationTargetType::ALL,
            'target_id' => null,
        ];
    }

    /**
     * Target every user.
     */
    public function all(): static
    {
        return $this->state(fn (): array => [
            'target_type' => NotificationTargetType::ALL,
            'target_id' => null,
        ]);
    }

    /**
     * Target a specific user.
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn (): array => [
            'target_type' => NotificationTargetType::USER,
            'target_id' => $userId,
        ]);
    }

    /**
     * Target a role.
     */
    public function forRole(int|string $roleId): static
    {
        return $this->state(fn (): array => [
            'target_type' => NotificationTargetType::ROLE,
            'target_id' => $roleId,
        ]);
    }
}
