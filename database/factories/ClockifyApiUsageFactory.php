<?php

namespace Database\Factories;

use App\Enums\ApiUsageWindowType;
use App\Models\ClockifyApiUsage;
use App\Models\ClockifyConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClockifyApiUsage>
 */
class ClockifyApiUsageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyApiUsage>
     */
    protected $model = ClockifyApiUsage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfHour();

        return [
            'connection_id' => ClockifyConnection::factory(),
            'workspace_id' => null,
            'window_type' => ApiUsageWindowType::HOUR,
            'window_started_at' => $start,
            'window_ends_at' => $start->copy()->addHour(),
            'requests_used' => 0,
            'requests_remaining' => 30,
            'limit_requests' => 30,
            'last_request_at' => null,
        ];
    }

    /**
     * A per-second (paid plan) window.
     */
    public function perSecond(): static
    {
        return $this->state(fn (): array => [
            'window_type' => ApiUsageWindowType::SECOND,
            'window_started_at' => now()->startOfSecond(),
            'window_ends_at' => now()->startOfSecond()->addSecond(),
            'requests_remaining' => 50,
            'limit_requests' => 50,
        ]);
    }
}
