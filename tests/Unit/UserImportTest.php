<?php

use App\Imports\UserImport;
use App\Models\DataProcessingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('chunk size is configurable and defaults to 100', function () {
    $job = DataProcessingJob::factory()->import()->create();

    config()->set('exports.import.chunk_size', 100);
    expect((new UserImport($job))->chunkSize())->toBe(100);

    config()->set('exports.import.chunk_size', 250);
    expect((new UserImport($job))->chunkSize())->toBe(250);
});
