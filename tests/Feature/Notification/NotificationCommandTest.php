<?php

use App\Models\Notification;

test('notifications:send creates a targeted notification', function () {
    $user = member();

    $this->artisan('notifications:send', [
        '--title' => 'Hello world',
        '--user' => [$user->id],
    ])->assertSuccessful();

    $this->assertDatabaseHas('notifications', ['title' => 'Hello world']);
    $this->assertDatabaseHas('notification_targets', [
        'target_type' => 'user',
        'target_id' => (string) $user->id,
    ]);
});

test('notifications:prune deletes old notifications and their targets', function () {
    $old = Notification::factory()->all()->create(['created_at' => now()->subDays(120)]);
    $fresh = Notification::factory()->all()->create();

    $this->artisan('notifications:prune')->assertSuccessful();

    $this->assertDatabaseMissing('notifications', ['id' => $old->id]);
    $this->assertDatabaseMissing('notification_targets', ['notification_id' => $old->id]);
    $this->assertDatabaseHas('notifications', ['id' => $fresh->id]);
});

test('notifications:prune supports a dry run', function () {
    Notification::factory()->all()->create(['created_at' => now()->subDays(120)]);

    $this->artisan('notifications:prune', ['--dry-run' => true])->assertSuccessful();

    expect(Notification::query()->count())->toBe(1);
});
