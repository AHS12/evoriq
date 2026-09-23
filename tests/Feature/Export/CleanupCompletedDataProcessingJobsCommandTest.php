<?php

use App\Models\DataProcessingJob;
use Illuminate\Support\Facades\Storage;

test('deletes completed jobs older than the retention window', function () {
    Storage::fake('local');

    $old = DataProcessingJob::factory()->completed()->create([
        'completed_at' => now()->subDays(30),
        'file_disk' => 'local',
        'file_path' => 'exports/old.csv',
    ]);

    Storage::disk('local')->put('exports/old.csv', 'content');

    $recent = DataProcessingJob::factory()->completed()->create([
        'completed_at' => now()->subDay(),
    ]);

    $this->artisan('data-processing:cleanup-completed', ['--days' => 7])
        ->assertSuccessful();

    $this->assertDatabaseMissing('data_processing_jobs', ['id' => $old->id]);
    $this->assertDatabaseHas('data_processing_jobs', ['id' => $recent->id]);

    Storage::disk('local')->assertMissing('exports/old.csv');
});

test('does not delete anything on a dry run', function () {
    $job = DataProcessingJob::factory()->completed()->create([
        'completed_at' => now()->subDays(30),
    ]);

    $this->artisan('data-processing:cleanup-completed', ['--days' => 7, '--dry-run' => true])
        ->assertSuccessful();

    $this->assertDatabaseHas('data_processing_jobs', ['id' => $job->id]);
});

test('leaves pending jobs untouched', function () {
    $job = DataProcessingJob::factory()->create([
        'status' => 'completed',
        'completed_at' => now()->subDays(30),
    ]);

    DataProcessingJob::factory()->create([
        'status' => 'pending',
        'completed_at' => null,
    ]);

    $this->artisan('data-processing:cleanup-completed', ['--days' => 7])
        ->assertSuccessful();

    $this->assertDatabaseMissing('data_processing_jobs', ['id' => $job->id]);
    $this->assertDatabaseHas('data_processing_jobs', ['status' => 'pending']);
});
