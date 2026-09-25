<?php

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Models\AuditActivity;
use App\Models\User;
use App\Services\Audit\AuditLogService;

/**
 * Seed one audit row caused by the given user.
 */
function auditRowFor(User $causer, string $description = 'Entry for test'): AuditActivity
{
    return AuditActivity::query()->create([
        'log_name' => 'domain',
        'event' => 'updated',
        'description' => $description,
        'causer_type' => (new User)->getMorphClass(),
        'causer_id' => $causer->id,
        'subject_type' => (new User)->getMorphClass(),
        'subject_id' => $causer->id,
        'properties' => ['correlation_id' => 'corr-1', 'ip_address' => '127.0.0.1'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('index is forbidden without an audit permission', function () {
    $this->actingAs(member());

    $this->get(route('audit-logs.index'))->assertForbidden();
});

test('index renders for a user with audit.view', function () {
    $this->actingAs(makeUserWithPermissions(['audit.view']));

    $this->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('audit-logs/index')
            ->has('logs.data')
            ->has('logs.meta.next_cursor')
            ->has('channels')
            ->has('events')
            ->where('canViewAll', false)
            ->where('canManage', false));
});

test('users with audit.view only see rows they caused', function () {
    $viewer = makeUserWithPermissions(['audit.view']);
    $other = User::factory()->create();

    auditRowFor($viewer, 'own entry');
    auditRowFor($other, 'foreign entry');

    $this->actingAs($viewer);

    $this->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.description', 'own entry'));
});

test('users with audit.view.all see every row', function () {
    $viewer = makeUserWithPermissions(['audit.view.all']);
    $other = User::factory()->create();

    auditRowFor($viewer, 'own entry');
    auditRowFor($other, 'foreign entry');

    $total = AuditActivity::query()->count();

    $this->actingAs($viewer);

    $this->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logs.data', $total));
});

test('show returns the detail resource for the causer', function () {
    $viewer = makeUserWithPermissions(['audit.view']);
    $row = auditRowFor($viewer, 'own entry');

    $this->actingAs($viewer);

    $this->get(route('audit-logs.show', $row))
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('data.id', $row->id)
            ->where('data.description', 'own entry')
            ->where('data.causer.id', $viewer->id)
            ->where('data.correlation_id', 'corr-1')
            ->etc());
});

test('show is forbidden for foreign rows without audit.view.all', function () {
    $viewer = makeUserWithPermissions(['audit.view']);
    $other = User::factory()->create();
    $row = auditRowFor($other);

    $this->actingAs($viewer);

    $this->get(route('audit-logs.show', $row))->assertForbidden();
});

test('prune is forbidden without audit.manage', function () {
    $this->actingAs(makeUserWithPermissions(['audit.view.all']));

    $this->post(route('audit-logs.prune'))->assertForbidden();
});

test('prune deletes entries past the retention window', function () {
    $manager = makeUserWithPermissions(['audit.manage']);

    $old = AuditActivity::query()->create([
        'log_name' => 'domain',
        'description' => 'ancient',
        'created_at' => now()->subDays(400),
        'updated_at' => now(),
    ]);

    $fresh = AuditActivity::query()->create([
        'log_name' => 'domain',
        'description' => 'recent',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($manager);

    $this->post(route('audit-logs.prune'))->assertRedirect();

    expect(AuditActivity::query()->whereKey($old->getKey())->exists())->toBeFalse()
        ->and(AuditActivity::query()->whereKey($fresh->getKey())->exists())->toBeTrue();
});

test('channel filter narrows the listing', function () {
    $viewer = makeUserWithPermissions(['audit.view.all']);

    auditRowFor($viewer, 'domain entry');

    app(AuditLogService::class)->record(AuditEvent::LOGIN, $viewer, channel: AuditLogName::AUTH);

    $this->actingAs($viewer);

    $this->get(route('audit-logs.index', ['channel' => 'auth']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.channel', 'auth'));
});

test('cursor pagination walks the full trail without duplicates', function () {
    $viewer = makeUserWithPermissions(['audit.view.all']);

    for ($i = 1; $i <= 35; $i++) {
        auditRowFor($viewer, "entry {$i}");
    }

    $this->actingAs($viewer);

    $expected = AuditActivity::query()->count();

    $seen = [];
    $cursor = null;

    do {
        $this->get(route('audit-logs.index', array_filter([
            'per_page' => 10,
            'cursor' => $cursor,
        ])))
            ->assertOk()
            ->assertInertia(function ($page) use (&$seen, &$cursor) {
                /** @var array<string, mixed> $logs */
                $logs = $page->toArray()['props']['logs'];

                foreach ($logs['data'] as $row) {
                    $seen[] = $row['id'];
                }

                /** @var array{next_cursor: ?string} $meta */
                $meta = $logs['meta'];

                $cursor = $meta['next_cursor'];
            });
    } while ($cursor !== null && count($seen) < $expected + 10);

    expect(count($seen))->toBe($expected)
        ->and(count($seen))->toBe(count(array_unique($seen)));
});
