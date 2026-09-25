<?php

namespace App\Listeners\Audit;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Records authentication and account-security events on the audit trail.
 *
 * Fortify/Laravel fire these events on the request thread, so the causer is
 * resolvable; the AuditRequestContext middleware already attached the IP,
 * user agent and correlation id to every row.
 */
final class AuthAuditSubscriber
{
    public function __construct(private readonly AuditLogService $audit)
    {
        //
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'handleLogin']);
        $events->listen(Logout::class, [self::class, 'handleLogout']);
        $events->listen(Failed::class, [self::class, 'handleFailed']);
        $events->listen(Lockout::class, [self::class, 'handleLockout']);
        $events->listen(PasswordReset::class, [self::class, 'handlePasswordReset']);
        $events->listen(PasswordUpdatedViaController::class, [self::class, 'handlePasswordUpdated']);
        $events->listen(Verified::class, [self::class, 'handleVerified']);
        $events->listen(TwoFactorAuthenticationEnabled::class, [self::class, 'handleTwoFactorEnabled']);
        $events->listen(TwoFactorAuthenticationDisabled::class, [self::class, 'handleTwoFactorDisabled']);
        $events->listen(TwoFactorAuthenticationFailed::class, [self::class, 'handleTwoFactorFailed']);
    }

    public function handleLogin(Login $event): void
    {
        $this->record(AuditEvent::LOGIN, $event->user, AuditLogName::AUTH, [
            'remember' => $event->remember,
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record(AuditEvent::LOGOUT, $event->user, AuditLogName::AUTH);
    }

    public function handleFailed(Failed $event): void
    {
        $this->record(AuditEvent::LOGIN_FAILED, $event->user, AuditLogName::SECURITY, [
            'attempted_email' => $event->credentials['email'] ?? null,
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->record(AuditEvent::LOCKOUT, null, AuditLogName::SECURITY, [
            'attempted_email' => $event->request->input('email'),
        ]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->record(AuditEvent::PASSWORD_RESET, $event->user, AuditLogName::SECURITY);
    }

    public function handlePasswordUpdated(PasswordUpdatedViaController $event): void
    {
        $this->record(AuditEvent::PASSWORD_UPDATED, $event->user, AuditLogName::SECURITY);
    }

    public function handleVerified(Verified $event): void
    {
        $this->record(
            AuditEvent::EMAIL_VERIFIED,
            $event->user instanceof Authenticatable ? $event->user : null,
            AuditLogName::SECURITY,
        );
    }

    public function handleTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->record(AuditEvent::TWO_FACTOR_ENABLED, $event->user, AuditLogName::SECURITY);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->record(AuditEvent::TWO_FACTOR_DISABLED, $event->user, AuditLogName::SECURITY);
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->record(AuditEvent::TWO_FACTOR_FAILED, $event->user, AuditLogName::SECURITY);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function record(AuditEvent $event, ?Authenticatable $user, AuditLogName $channel, array $properties = []): void
    {
        $actor = $user instanceof User ? $user : null;

        $this->audit->record($event, $actor, $properties, $actor, $channel);
    }
}
