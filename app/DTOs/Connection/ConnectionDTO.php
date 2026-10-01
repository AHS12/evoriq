<?php

namespace App\DTOs\Connection;

use App\Enums\ApiRegion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Typed input for creating/updating a Clockify connection (CONN-01). Base URLs
 * are resolved from the region by the service, never supplied by the client.
 */
final readonly class ConnectionDTO
{
    /**
     * @param  array<string, mixed>|null  $features
     */
    public function __construct(
        public string $name,
        public string $apiKey,
        public ?string $addonToken = null,
        public ApiRegion $region = ApiRegion::GLOBAL,
        public ?string $subdomain = null,
        public ?string $workspaceId = null,
        public ?string $featureSubscriptionType = null,
        public ?array $features = null,
        public ?int $webhookLimit = null,
        public ?int $requestsPerHour = null,
        public ?int $requestsPerSecond = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            name: (string) $data['name'],
            apiKey: (string) $data['api_key'],
            addonToken: isset($data['addon_token']) ? (string) $data['addon_token'] : null,
            region: isset($data['region']) ? ApiRegion::from((string) $data['region']) : ApiRegion::GLOBAL,
            subdomain: isset($data['subdomain']) ? (string) $data['subdomain'] : null,
            workspaceId: isset($data['workspace_id']) ? (string) $data['workspace_id'] : null,
            featureSubscriptionType: isset($data['feature_subscription_type']) ? (string) $data['feature_subscription_type'] : null,
            features: isset($data['features']) && is_array($data['features']) ? $data['features'] : null,
            webhookLimit: isset($data['webhook_limit']) ? (int) $data['webhook_limit'] : null,
            requestsPerHour: isset($data['requests_per_hour']) ? (int) $data['requests_per_hour'] : null,
            requestsPerSecond: isset($data['requests_per_second']) ? (int) $data['requests_per_second'] : null,
        );
    }

    /**
     * The fillable attributes (base URLs are added by the service).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'api_key' => $this->apiKey,
            'addon_token' => $this->addonToken,
            'region' => $this->region->value,
            'subdomain' => $this->subdomain,
            'workspace_id' => $this->workspaceId,
            'feature_subscription_type' => $this->featureSubscriptionType,
            'features' => $this->features,
            'webhook_limit' => $this->webhookLimit,
            'requests_per_hour' => $this->requestsPerHour,
            'requests_per_second' => $this->requestsPerSecond,
        ];
    }
}
