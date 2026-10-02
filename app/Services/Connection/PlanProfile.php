<?php

namespace App\Services\Connection;

/**
 * The detected Clockify plan and its request budget (CONN-02). Free plans get
 * an hourly budget; paid plans get a per-second one. Detection is rule-based
 * and conservative: an unknown plan is treated as Free.
 */
final readonly class PlanProfile
{
    public function __construct(
        public string $plan,
        public ?int $requestsPerHour,
        public ?int $requestsPerSecond,
        public ?int $webhookLimit,
    ) {}

    /**
     * @param  array<string, mixed>  $workspace
     */
    public static function fromWorkspace(array $workspace): self
    {
        $subscription = strtoupper((string) ($workspace['featureSubscriptionType'] ?? ''));
        $features = array_map(
            static fn (mixed $feature): string => strtoupper((string) $feature),
            array_values((array) ($workspace['features'] ?? [])),
        );

        $webhookLimit = isset($workspace['webhookLimit']) ? (int) $workspace['webhookLimit'] : null;

        if (self::looksPaid($subscription, $features)) {
            return new self(
                plan: 'paid',
                requestsPerHour: null,
                requestsPerSecond: (int) config('clockify.rate_limit.requests_per_second', 50),
                webhookLimit: $webhookLimit,
            );
        }

        return new self(
            plan: 'free',
            requestsPerHour: (int) config('clockify.rate_limit.free_requests_per_hour', 30),
            requestsPerSecond: null,
            webhookLimit: $webhookLimit,
        );
    }

    public function isFree(): bool
    {
        return $this->plan === 'free';
    }

    /**
     * @return array{plan: string, requests_per_hour: int|null, requests_per_second: int|null, webhook_limit: int|null}
     */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
            'requests_per_hour' => $this->requestsPerHour,
            'requests_per_second' => $this->requestsPerSecond,
            'webhook_limit' => $this->webhookLimit,
        ];
    }

    /**
     * @param  array<int, string>  $features
     */
    private static function looksPaid(string $subscription, array $features): bool
    {
        foreach (['STANDARD', 'PRO', 'ENTERPRISE', 'BUSINESS', 'PAID'] as $needle) {
            if (str_contains($subscription, $needle)) {
                return true;
            }
        }

        foreach (['REPORTS', 'INVOICES', 'EXPENSES', 'TIME_OFF', 'SCHEDULING', 'APPROVAL', 'AUDIT_LOG'] as $feature) {
            if (in_array($feature, $features, true)) {
                return true;
            }
        }

        return false;
    }
}
