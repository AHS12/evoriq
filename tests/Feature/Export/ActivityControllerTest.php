<?php

use App\Enums\DataProcessingJobStatus;
use App\Jobs\ProcessExport;
use App\Jobs\ProcessImport;
use App\Models\DataProcessingJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Mock\ExportMockData;
use Tests\Mock\ImportMockData;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'data-processing.view',
        'data-processing.view.all',
        'data-processing.manage',
        'data-processing.delete',
        'user.export',
        'user.import',
    ]);
});

test('renders the job center', function () {
    DataProcessingJob::factory()->count(2)->create();

    $this->actingAs($this->user)
        ->get(route('activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('data-processing/index')
            ->has('jobs')
            ->has('stats')
            ->has('activeJobs')
            ->has('options'));
});

test('queues an export and flashes the confirmation', function () {
    $this->actingAs($this->user)
        ->post(route('activity.storeExport'), ExportMockData::request())
        ->assertRedirect()
        ->assertSessionHas(
            'inertia.flash_data',
            fn (array $flash): bool => isset($flash['job_queued']['job_id']),
        );

    Queue::assertPushed(ProcessExport::class);
});

test('queues an import from an uploaded file', function () {
    Storage::fake('local');

    $this->actingAs($this->user)
        ->post(route('activity.storeImport'), [
            'entity_type' => 'users',
            'file' => UploadedFile::fake()->createWithContent('users.csv', ImportMockData::csv()),
            'filters' => ['send_invitations' => false],
        ])
        ->assertRedirect();

    Queue::assertPushed(ProcessImport::class);

    $this->assertDatabaseHas('data_processing_jobs', [
        'type' => 'import',
        'entity_type' => 'users',
        'user_id' => $this->user->id,
    ]);
});

test('downloads an import template', function () {
    $this->actingAs($this->user)
        ->get(route('activity.template', ['entity' => 'users']))
        ->assertOk()
        ->assertDownload('users_import_template.csv');
});

test('cancels a pending job', function () {
    $job = DataProcessingJob::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->post(route('activity.cancel', $job))
        ->assertRedirect();

    expect($job->refresh()->status)->toBe(DataProcessingJobStatus::CANCELLED);
});

test('retries a failed job', function () {
    $job = DataProcessingJob::factory()->failed()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->post(route('activity.retry', $job))
        ->assertRedirect();

    Queue::assertPushed(ProcessExport::class);
});

test('duplicates a job', function () {
    $job = DataProcessingJob::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->post(route('activity.duplicate', $job))
        ->assertRedirect();

    expect(DataProcessingJob::count())->toBe(2);
});

test('deletes a job', function () {
    $job = DataProcessingJob::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->delete(route('activity.destroy', $job))
        ->assertRedirect();

    $this->assertDatabaseMissing('data_processing_jobs', ['id' => $job->id]);
});

test('forbids users without access', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('activity.index'))
        ->assertForbidden();
});
