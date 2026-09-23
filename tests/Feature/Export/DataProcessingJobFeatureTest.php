<?php

use App\Jobs\ProcessExport;
use App\Models\DataProcessingJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Mock\ExportMockData;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['export.view', 'export.view.all', 'export.create', 'export.delete']);
});

test('lists data processing jobs', function () {
    DataProcessingJob::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->getJson(route('exports.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'job_id', 'status', 'entity_type']]]);
});

test('queues an export job', function () {
    $this->actingAs($this->user)
        ->postJson(route('exports.store'), ExportMockData::request())
        ->assertCreated()
        ->assertJsonPath('data.entity_type', 'users')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('data_processing_jobs', [
        'entity_type' => 'users',
        'status' => 'pending',
        'user_id' => $this->user->id,
    ]);

    Queue::assertPushed(ProcessExport::class);
});

test('rejects an invalid export payload', function () {
    $this->actingAs($this->user)
        ->postJson(route('exports.store'), ['entity_type' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entity_type', 'format']);
});

test('downloads a completed export file', function () {
    Storage::fake('local');

    $job = DataProcessingJob::factory()->completed()->create([
        'file_name' => 'users.xlsx',
        'file_path' => 'exports/users.xlsx',
        'file_disk' => 'local',
    ]);

    Storage::disk('local')->put('exports/users.xlsx', 'file-content');

    $this->actingAs($this->user)
        ->get(route('exports.download', $job))
        ->assertOk()
        ->assertDownload('users.xlsx');
});

test('returns not found when the export file is missing', function () {
    Storage::fake('local');

    $job = DataProcessingJob::factory()->completed()->create([
        'file_path' => 'exports/missing.xlsx',
        'file_disk' => 'local',
    ]);

    $this->actingAs($this->user)
        ->getJson(route('exports.download', $job))
        ->assertNotFound();
});

test('deletes a data processing job', function () {
    $job = DataProcessingJob::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson(route('exports.destroy', $job))
        ->assertNoContent();

    $this->assertDatabaseMissing('data_processing_jobs', ['id' => $job->id]);
});

test('forbids users without export permissions', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->getJson(route('exports.index'))
        ->assertForbidden();
});
