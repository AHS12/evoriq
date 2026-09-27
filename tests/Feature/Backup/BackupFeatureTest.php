<?php

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Jobs\Backup\RunBackup;
use App\Mail\BackupCompletedMail;
use App\Models\AuditActivity;
use App\Models\BackupRun;
use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('the backup page renders for users with backup.view', function () {
    $this->actingAs(admin());

    $this->get(route('admin.settings.backup.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/backup')
            ->has('group.fields', 10)
            ->has('status')
            ->has('runs.data'));
});

test('the backup page is forbidden without backup.view', function () {
    $this->actingAs(member());

    $this->get(route('admin.settings.backup.edit'))->assertForbidden();
});

test('backup settings are validated and persisted through the shared settings controller', function () {
    $this->actingAs(admin());

    $this->from(route('admin.settings.backup.edit'))
        ->patch(route('admin.settings.backup.update'), [
            'backup_enabled' => '1',
            'backup_frequency' => 'weekly',
            'backup_time' => '02:30', // not an allowed slot
            'backup_send_to_remote' => '0',
            'backup_keep_days' => '30',
            'backup_max_storage_mb' => '2048',
            'backup_max_age_days' => '2',
            'backup_notify_on_failure' => '1',
            'backup_email_enabled' => '0',
            'backup_email_recipients' => 'ops@example.com',
        ])
        ->assertSessionHasErrors('backup_time');

    $this->from(route('admin.settings.backup.edit'))
        ->patch(route('admin.settings.backup.update'), [
            'backup_enabled' => '1',
            'backup_frequency' => 'weekly',
            'backup_time' => '04:30',
            'backup_send_to_remote' => '1',
            'backup_keep_days' => '30',
            'backup_max_storage_mb' => '2048',
            'backup_max_age_days' => '2',
            'backup_notify_on_failure' => '0',
            'backup_email_enabled' => '1',
            'backup_email_recipients' => 'not-an-email',
        ])
        ->assertSessionHasErrors('backup_email_recipients');

    $this->from(route('admin.settings.backup.edit'))
        ->patch(route('admin.settings.backup.update'), [
            'backup_enabled' => '1',
            'backup_frequency' => 'weekly',
            'backup_time' => '04:30',
            'backup_send_to_remote' => '1',
            'backup_keep_days' => '30',
            'backup_max_storage_mb' => '2048',
            'backup_max_age_days' => '2',
            'backup_notify_on_failure' => '0',
            'backup_email_enabled' => '1',
            'backup_email_recipients' => 'ops@example.com,admin@example.com',
        ])
        ->assertRedirect(route('admin.settings.backup.edit'));

    expect(Settings::get(SettingKey::BACKUP_FREQUENCY->value))->toBe('weekly')
        ->and(Settings::get(SettingKey::BACKUP_SEND_TO_REMOTE->value))->toBe('1')
        ->and(Settings::get(SettingKey::BACKUP_KEEP_DAYS->value))->toBe('30')
        ->and(Settings::get(SettingKey::BACKUP_NOTIFY_ON_FAILURE->value))->toBe('0')
        ->and(Settings::get(SettingKey::BACKUP_EMAIL_RECIPIENTS->value))->toBe('ops@example.com,admin@example.com');
});

test('run now queues a manual backup run and audits it', function () {
    Queue::fake();

    $user = admin();

    $this->actingAs($user);

    $this->from(route('admin.settings.backup.edit'))
        ->post(route('admin.settings.backup.run'))
        ->assertRedirect();

    Queue::assertPushed(RunBackup::class);

    $run = BackupRun::query()->sole();

    expect($run->trigger->value)->toBe('manual')
        ->and($run->status->value)->toBe('pending')
        ->and($run->user_id)->toBe($user->id)
        ->and(AuditActivity::query()->where('event', 'backup_triggered')->exists())->toBeTrue();
});

test('run now is refused while another backup is running', function () {
    Queue::fake();

    BackupRun::factory()->running()->create();

    $this->actingAs(admin());

    $this->post(route('admin.settings.backup.run'))
        ->assertRedirect();

    Queue::assertNotPushed(RunBackup::class);

    expect(BackupRun::query()->count())->toBe(1);
});

test('a completed backup can be downloaded from the destination disk', function () {
    Storage::fake('backups');

    $run = BackupRun::factory()->create([
        'status' => 'completed',
        'file_name' => 'Laravel React Starter/2026-09-27-01-30-00.zip',
    ]);

    Storage::disk('backups')->put($run->file_name, 'zip-bytes');

    $this->actingAs(admin());

    $response = $this->get(route('admin.settings.backup.download', $run));

    $response->assertOk();
});

test('a pending or failed run cannot be downloaded', function () {
    $run = BackupRun::factory()->create(['status' => 'failed']);

    $this->actingAs(admin());

    $this->get(route('admin.settings.backup.download', $run))->assertNotFound();
});

test('the schedule tick dispatches a backup when due and runs maintenance inline', function () {
    Queue::fake();

    // A time slot that has always passed, and a last run from yesterday, make
    // the backup due regardless of when this test executes.
    Settings::set(SettingKey::BACKUP_TIME->value, '00:30');

    BackupRun::factory()
        ->completed()
        ->create()
        ->forceFill(['created_at' => now()->subDay()->subHour()])
        ->save();

    $this->artisan('backup:schedule-tick')->assertSuccessful();

    Queue::assertPushed(RunBackup::class);

    expect(BackupRun::query()->where('type', 'cleanup')->exists())->toBeTrue()
        ->and(BackupRun::query()->where('type', 'monitor')->exists())->toBeTrue();
});

test('the schedule tick dispatches nothing when backups are disabled', function () {
    Queue::fake();

    Settings::set(SettingKey::BACKUP_ENABLED->value, false);

    $this->artisan('backup:schedule-tick')->assertSuccessful();

    Queue::assertNotPushed(RunBackup::class);

    expect(BackupRun::query()->where('type', 'backup')->count())->toBe(0)
        // Health checks still run so a disabled schedule surfaces stale backups.
        ->and(BackupRun::query()->where('type', 'monitor')->exists())->toBeTrue();
});

test('a failing backup reaches a terminal failed state with a notification', function () {
    Queue::fake();

    Settings::set(SettingKey::BACKUP_NOTIFY_ON_FAILURE->value, true);

    $run = BackupRun::factory()->create(['user_id' => admin()->id]);

    app(BackupService::class)->markFailed($run, 'pg_dump not found');

    $run->refresh();

    expect($run->status->value)->toBe('failed')
        ->and($run->error_message)->toBe('pg_dump not found')
        ->and($run->completed_at)->not->toBeNull()
        ->and(AuditActivity::query()->where('event', 'backup_failed')->exists())->toBeTrue();
});

test('a completed backup is emailed to backup managers when enabled', function () {
    Storage::fake('backups');
    Mail::fake();

    Settings::set(SettingKey::BACKUP_EMAIL_ENABLED->value, true);

    // The seeded super admin plus one admin hold backup.manage.
    admin();

    Storage::disk('backups')->put('Laravel React Starter/2026-09-27-01-30-00.zip', 'zip-bytes');

    $run = BackupRun::factory()->create([
        'status' => 'running',
        'file_name' => 'Laravel React Starter/2026-09-27-01-30-00.zip',
        'file_size' => 1024 * 1024, // 1 MB, small enough to attach
    ]);

    app(BackupService::class)->markCompleted($run);

    Mail::assertQueued(BackupCompletedMail::class, 2);
});

test('a static BACKUP_EMAIL_TO recipient receives the archive instead of the managers', function () {
    Storage::fake('backups');
    Mail::fake();

    Settings::set(SettingKey::BACKUP_EMAIL_ENABLED->value, true);

    config(['backup.notifications.mail_to' => 'ops@example.com,admin@example.com']);

    admin();

    Storage::disk('backups')->put('Laravel React Starter/2026-09-27-01-30-00.zip', 'zip-bytes');

    $run = BackupRun::factory()->create([
        'status' => 'running',
        'file_name' => 'Laravel React Starter/2026-09-27-01-30-00.zip',
        'file_size' => 1024 * 1024,
    ]);

    app(BackupService::class)->markCompleted($run);

    // Only the two static addresses — the super admin and admin are ignored.
    Mail::assertQueued(BackupCompletedMail::class, 2);
});

test('the stored email recipients setting wins over the env fallback and the managers', function () {
    Storage::fake('backups');
    Mail::fake();

    Settings::set(SettingKey::BACKUP_EMAIL_ENABLED->value, true);
    Settings::set(SettingKey::BACKUP_EMAIL_RECIPIENTS->value, 'owner@example.com');

    config(['backup.notifications.mail_to' => 'ops@example.com']);

    admin();

    Storage::disk('backups')->put('Laravel React Starter/2026-09-27-01-30-00.zip', 'zip-bytes');

    $run = BackupRun::factory()->create([
        'status' => 'running',
        'file_name' => 'Laravel React Starter/2026-09-27-01-30-00.zip',
        'file_size' => 1024 * 1024,
    ]);

    app(BackupService::class)->markCompleted($run);

    Mail::assertQueued(BackupCompletedMail::class, 1);
});

test('the archive email is skipped when the setting is off', function () {
    Storage::fake('backups');
    Mail::fake();

    Settings::set(SettingKey::BACKUP_EMAIL_ENABLED->value, false);

    admin();

    Storage::disk('backups')->put('Laravel React Starter/2026-09-27-01-30-00.zip', 'zip-bytes');

    $run = BackupRun::factory()->create([
        'status' => 'running',
        'file_name' => 'Laravel React Starter/2026-09-27-01-30-00.zip',
        'file_size' => 1024 * 1024,
    ]);

    app(BackupService::class)->markCompleted($run);

    Mail::assertNothingQueued();
});

test('oversized archives are emailed without attachment logic failing the run', function () {
    Storage::fake('backups');
    Mail::fake();

    Settings::set(SettingKey::BACKUP_EMAIL_ENABLED->value, true);

    admin();

    Storage::disk('backups')->put('Laravel React Starter/2026-09-27-01-30-00.zip', 'zip-bytes');

    $run = BackupRun::factory()->create([
        'status' => 'running',
        'file_name' => 'Laravel React Starter/2026-09-27-01-30-00.zip',
        'file_size' => 500 * 1024 * 1024, // far above the 10 MB attachment limit
    ]);

    app(BackupService::class)->markCompleted($run);

    // The email is still queued (without the attachment), and the run
    // completes normally.
    Mail::assertQueued(BackupCompletedMail::class, 2);

    $run->refresh();

    expect($run->status->value)->toBe('completed');
});
