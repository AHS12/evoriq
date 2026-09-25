<?php

/*
|--------------------------------------------------------------------------
| Audit Log Configuration
|--------------------------------------------------------------------------
|
| Application-level audit trail settings. The collector itself is
| spatie/laravel-activitylog (see config/activitylog.php); this config owns
| retention per log channel, UI pagination and the central redaction list.
|
*/

return [

    /*
     * Retention in days per audit log channel (App\Enums\AuditLogName).
     * Pruned nightly by the `audit:clean` command.
     *
     * These values are fallbacks only: per-channel retention is managed at
     * runtime through Administration → Settings → Audit log (SettingKey
     * AUDIT_RETENTION_*, resolved in AuditLogName::retentionDays()).
     */
    'retention' => [
        'auth' => 180,
        'security' => 365,
        'rbac' => 365,
        'settings' => 180,
        'domain' => 90,
    ],

    /*
     * Entries per page in the audit log UI (cursor pagination).
     */
    'pagination' => 25,

    /*
     * Attribute name patterns that must never reach the audit log, on any
     * model, in diffs or properties. Matched with Str::is (supports *).
     */
    'redacted_attributes' => [
        'password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        '*token',
        '*secret*',
        'card_number',
        'ssn',
    ],

];
