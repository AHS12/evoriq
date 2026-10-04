<?php

namespace App\Enums;

/**
 * The kind of Clockify membership a user holds (ENT-02, ENT-04, ENT-12). Stored
 * lower-case so project and user-group memberships can share the table later.
 */
enum ClockifyMembershipType: string
{
    case WORKSPACE = 'workspace';
    case PROJECT = 'project';
    case USER_GROUP = 'user_group';

    public function label(): string
    {
        return match ($this) {
            self::WORKSPACE => __('Workspace'),
            self::PROJECT => __('Project'),
            self::USER_GROUP => __('User group'),
        };
    }

    /**
     * Resolve an API `membershipType` value, tolerating casing/shape variance.
     */
    public static function fromApi(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        return match (strtoupper(str_replace(['_', ' '], '', $value))) {
            'WORKSPACE' => self::WORKSPACE,
            'PROJECT' => self::PROJECT,
            'USERGROUP' => self::USER_GROUP,
            default => null,
        };
    }
}
