<?php

use App\Jobs\ProcessExport;
use App\Jobs\ProcessImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Mock\ImportMockData;

test('shares the processing options with the users page', function () {
    $user = makeUserWithPermissions([
        'user.view.all',
        'user.export',
        'user.import',
    ]);

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('processingOptions.entities')
            ->has('processingOptions.formats')
            ->has('processingOptions.roles'));
});

test('a user with the module export permission can queue a user export', function () {
    Queue::fake();

    $user = makeUserWithPermissions(['user.view.all', 'user.export']);

    $this->actingAs($user)
        ->post(route('activity.storeExport'), [
            'entity_type' => 'users',
            'format' => 'csv',
            'filters' => ['search' => 'ada', 'status' => 'active'],
        ])
        ->assertRedirect();

    Queue::assertPushed(ProcessExport::class);

    $this->assertDatabaseHas('data_processing_jobs', [
        'type' => 'export',
        'entity_type' => 'users',
        'user_id' => $user->id,
    ]);
});

test('a user with the module import permission can queue a user import', function () {
    Queue::fake();
    Storage::fake('local');

    $user = makeUserWithPermissions(['user.view.all', 'user.import']);

    $this->actingAs($user)
        ->post(route('activity.storeImport'), [
            'entity_type' => 'users',
            'file' => UploadedFile::fake()->createWithContent('users.csv', ImportMockData::csv()),
        ])
        ->assertRedirect();

    Queue::assertPushed(ProcessImport::class);
});

test('a user without create permissions cannot queue a user export', function () {
    $user = makeUserWithPermissions(['data-processing.view', 'user.view.all']);

    $this->actingAs($user)
        ->post(route('activity.storeExport'), [
            'entity_type' => 'users',
            'format' => 'csv',
        ])
        ->assertForbidden();
});
