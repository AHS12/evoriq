<?php

use App\Enums\DataProcessingJobStatus;
use App\Enums\NotificationType;
use App\Models\DataProcessingJob;
use App\Models\Notification;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Inertia\Testing\AssertableInertia as Assert;

test('a finished job notification reaches the owner feed with a working link', function () {
    $user = makeUser();

    $job = DataProcessingJob::factory()->create([
        'user_id' => $user->id,
        'status' => DataProcessingJobStatus::COMPLETED,
    ]);

    app(DataProcessingJobService::class)->notifyFinished($job);

    $notification = Notification::query()->firstOrFail();

    expect($notification->type)->toBe(NotificationType::EXPORT_COMPLETED->value)
        ->and($notification->action_url)->not->toBeNull()
        ->and(str_starts_with((string) $notification->action_url, route('activity.index')))->toBeTrue();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('feed.data', 1));
});

test('muting the pipeline category keeps the notification out of the feed', function () {
    $user = makeUser();
    $user->settings()->set('notifications.muted_categories', json_encode(['pipeline']));

    $job = DataProcessingJob::factory()->create([
        'user_id' => $user->id,
        'status' => DataProcessingJobStatus::COMPLETED,
    ]);

    app(DataProcessingJobService::class)->notifyFinished($job);

    expect(Notification::query()->count())->toBe(0);
});
