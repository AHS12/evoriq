<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;

/**
 * Resolves the organization the current request, job or console command
 * operates against.
 *
 * Resolution order: an explicitly selected organization (tests/console),
 * then the authenticated user's organization, then the default organization.
 * The result is memoized per container instance.
 */
class OrganizationContext
{
    /**
     * Whether the current organization has been resolved yet.
     */
    protected bool $resolved = false;

    /**
     * The resolved organization.
     */
    protected ?Organization $organization = null;

    /**
     * The current organization id, if one can be resolved.
     */
    public function id(): int|string|null
    {
        return $this->current()?->getKey();
    }

    /**
     * The current organization, if one can be resolved.
     */
    public function model(): ?Organization
    {
        return $this->current();
    }

    /**
     * Force the context to a specific organization (tests/console).
     */
    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
        $this->resolved = true;
    }

    /**
     * Clear any resolved state so the next call re-resolves.
     */
    public function reset(): void
    {
        $this->organization = null;
        $this->resolved = false;
    }

    /**
     * Resolve (and memoize) the current organization.
     */
    protected function current(): ?Organization
    {
        if ($this->resolved) {
            return $this->organization;
        }

        // Mark resolved before resolving to break re-entrancy: the global
        // scope on organization-owned models (User included) calls id() again
        // while the authenticated user is being retrieved.
        $this->resolved = true;

        $this->organization = $this->resolveUserOrganization() ?? Organization::default();

        return $this->organization;
    }

    /**
     * The organization of the authenticated user, if any.
     */
    protected function resolveUserOrganization(): ?Organization
    {
        if (! app()->bound('auth')) {
            return null;
        }

        $user = auth()->user();

        if (! $user instanceof User || $user->organization_id === null) {
            return null;
        }

        return Organization::query()->find($user->organization_id);
    }
}
