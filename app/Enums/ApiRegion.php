<?php

namespace App\Enums;

/**
 * A Clockify API region (CONN-01). Each region resolves the regular and reports
 * API base URLs; subdomain workspaces override the reports host.
 *
 * @see spec/reference/clockify-api.md §2
 */
enum ApiRegion: string
{
    case GLOBAL = 'global';
    case EU = 'eu';
    case US = 'us';
    case UK = 'uk';
    case AU = 'au';
    case DEVELOPER = 'developer';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => __('Global'),
            self::EU => __('Europe'),
            self::US => __('United States'),
            self::UK => __('United Kingdom'),
            self::AU => __('Australia'),
            self::DEVELOPER => __('Developer'),
        };
    }

    /**
     * The regional host (without scheme or path).
     */
    public function host(): string
    {
        return match ($this) {
            self::GLOBAL => 'api.clockify.me',
            self::EU => 'euc1.clockify.me',
            self::US => 'use2.clockify.me',
            self::UK => 'euw2.clockify.me',
            self::AU => 'apse2.clockify.me',
            self::DEVELOPER => 'developer.clockify.me',
        };
    }

    /**
     * The regular REST API base URL.
     */
    public function baseUrl(): string
    {
        return "https://{$this->host()}/api/v1";
    }

    /**
     * The reports API base URL. The global region uses a dedicated host.
     */
    public function reportsBaseUrl(): string
    {
        if ($this === self::GLOBAL) {
            return 'https://reports.api.clockify.me/v1';
        }

        return "https://{$this->host()}/report/v1";
    }

    /**
     * The reports base URL for a connection, honouring a subdomain workspace.
     */
    public function reportsBaseUrlFor(?string $subdomain): string
    {
        if ($subdomain !== null && $subdomain !== '') {
            return "https://{$subdomain}.clockify.me/report/v1";
        }

        return $this->reportsBaseUrl();
    }
}
