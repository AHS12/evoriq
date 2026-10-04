<?php

namespace App\Support\Clockify;

/**
 * Maps a raw Clockify workspace payload into our workspace dimension columns
 * (CONN-03 discovery, ENT-01 enrichment). Clockify's shape varies between
 * plans/versions — `subdomain` is a string or object, `currencies` carries the
 * default code, `workspaceSettings` holds the time zone/week start, and the
 * rate fields may be scalars or `{amount, since}` — so every field is optional
 * and tolerant of nulls.
 */
final class WorkspaceMapper
{
    /**
     * @param  array<string, mixed>  $workspace
     * @return array<string, mixed>
     */
    public function map(array $workspace): array
    {
        return [
            'clockify_id' => (string) ($workspace['id'] ?? ''),
            'name' => (string) ($workspace['name'] ?? 'Workspace'),
            'subdomain' => $this->subdomain($workspace),
            'currency' => $this->currency($workspace),
            'time_zone' => $this->timeZone($workspace),
            'week_start' => $this->weekStart($workspace),
            'default_billable' => $this->boolean($workspace['defaultBillable'] ?? null),
            'default_hourly_rate' => $this->rate($workspace['hourlyRate'] ?? $workspace['defaultHourlyRate'] ?? null),
            'default_cost_rate' => $this->rate($workspace['costRate'] ?? $workspace['defaultCostRate'] ?? null),
            'feature_subscription_type' => $this->string($workspace['featureSubscriptionType'] ?? null),
            'cake_organization_id' => $this->string($workspace['cakeOrganizationId'] ?? null),
            'features' => $this->stringList($workspace['features'] ?? null),
            'workspace_settings' => $this->settings($workspace),
        ];
    }

    /**
     * Clockify returns `subdomain` as a string (older) or `{ name, enabled }`.
     *
     * @param  array<string, mixed>  $workspace
     */
    private function subdomain(array $workspace): ?string
    {
        $subdomain = $workspace['subdomain'] ?? null;

        if (is_array($subdomain)) {
            return $this->string($subdomain['name'] ?? null);
        }

        return $this->string($subdomain);
    }

    /**
     * Prefer a top-level `currency`, else the default entry of `currencies`.
     *
     * @param  array<string, mixed>  $workspace
     */
    private function currency(array $workspace): ?string
    {
        $currency = $this->string($workspace['currency'] ?? null);

        if ($currency !== null) {
            return $currency;
        }

        $currencies = $workspace['currencies'] ?? null;

        if (is_array($currencies)) {
            foreach ($currencies as $entry) {
                if (is_array($entry) && ($entry['isDefault'] ?? false) && isset($entry['code'])) {
                    return (string) $entry['code'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    private function timeZone(array $workspace): ?string
    {
        return $this->string($workspace['timeZone'] ?? null)
            ?? $this->string(data_get($workspace, 'workspaceSettings.timeZone'));
    }

    /**
     * @param  array<string, mixed>  $workspace
     */
    private function weekStart(array $workspace): ?string
    {
        return $this->string($workspace['weekStart'] ?? null)
            ?? $this->string(data_get($workspace, 'workspaceSettings.weekStart'));
    }

    /**
     * @param  array<string, mixed>  $workspace
     * @return array<string, mixed>|null
     */
    private function settings(array $workspace): ?array
    {
        $settings = $workspace['workspaceSettings'] ?? null;

        return is_array($settings) ? $settings : null;
    }

    /**
     * Accept a scalar amount or Clockify's `{amount, since}` / `{amount,
     * currency}` shape.
     */
    private function rate(mixed $value): ?float
    {
        if (is_array($value)) {
            $value = $value['amount'] ?? null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function boolean(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>|null
     */
    private function stringList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item)));
    }
}
