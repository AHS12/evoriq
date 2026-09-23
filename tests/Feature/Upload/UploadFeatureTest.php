<?php

use App\Enums\MediaCollection;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Mock\UploadMockData;

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['file.view', 'file.create', 'file.delete']);
});

test('lists uploads', function () {
    Upload::factory()->count(2)->create(['created_by' => $this->user->id]);

    $this->actingAs($this->user)
        ->get(route('files.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('files/index')
            ->has('files.data', 2));
});

test('stores an uploaded file', function () {
    $this->actingAs($this->user)
        ->post(route('files.store'), [
            'file' => UploadMockData::imageFile(),
        ])
        ->assertRedirect(route('files.index'));

    $this->assertDatabaseCount('uploads', 1);

    $upload = Upload::firstOrFail();

    expect($upload->type->value)->toBe('image')
        ->and($upload->created_by)->toBe($this->user->id);

    $media = $upload->getFirstMedia(MediaCollection::UPLOAD->value);

    expect($media)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $media?->getPathRelativeToRoot());
});

test('deletes an upload', function () {
    $upload = Upload::factory()->create(['created_by' => $this->user->id]);

    $this->actingAs($this->user)
        ->delete(route('files.destroy', $upload))
        ->assertRedirect(route('files.index'));

    $this->assertDatabaseMissing('uploads', ['id' => $upload->id]);
});

test('rejects a missing file', function () {
    $this->actingAs($this->user)
        ->post(route('files.store'), [])
        ->assertSessionHasErrors('file');
});

test('forbids users without file permissions', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('files.index'))
        ->assertForbidden();
});
