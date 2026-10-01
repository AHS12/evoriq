<?php

namespace Database\Factories;

use App\Enums\ApiRegion;
use App\Enums\ConnectionStatus;
use App\Models\ClockifyConnection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClockifyConnection>
 */
class ClockifyConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ClockifyConnection>
     */
    protected $model = ClockifyConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $region = ApiRegion::GLOBAL;

        return [
            'name' => fake()->unique()->company().' Clockify',
            'api_key' => 'key-'.Str::random(32),
            'addon_token' => null,
            'region' => $region,
            'base_url' => $region->baseUrl(),
            'reports_base_url' => $region->reportsBaseUrl(),
            'subdomain' => null,
            'workspace_id' => null,
            'feature_subscription_type' => 'STANDARD_2021',
            'features' => [],
            'webhook_limit' => 10,
            'requests_per_hour' => null,
            'requests_per_second' => 50,
            'status' => ConnectionStatus::ACTIVE,
            'last_verified_at' => now(),
            'last_error' => null,
            'created_by' => null,
        ];
    }

    /**
     * A Free-plan connection (hourly request budget).
     */
    public function free(): static
    {
        return $this->state(fn (): array => [
            'feature_subscription_type' => 'FREE',
            'requests_per_hour' => 30,
            'requests_per_second' => null,
        ]);
    }

    /**
     * A disabled connection.
     */
    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'status' => ConnectionStatus::DISABLED,
        ]);
    }

    /**
     * Pin the connection to a specific API region and its resolved URLs.
     */
    public function forRegion(ApiRegion $region, ?string $subdomain = null): static
    {
        return $this->state(fn (): array => [
            'region' => $region,
            'subdomain' => $subdomain,
            'base_url' => $region->baseUrl(),
            'reports_base_url' => $region->reportsBaseUrlFor($subdomain),
        ]);
    }
}
