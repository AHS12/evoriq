<?php

use App\DTOs\Upload\UploadDTO;
use App\DTOs\Upload\UploadFilterDTO;
use App\Enums\UploadType;
use App\Models\Upload;
use App\Repositories\Contracts\UploadRepositoryInterface;
use App\Services\Upload\UploadService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Mock\UploadMockData;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(UploadRepositoryInterface::class);
    $this->service = new UploadService($this->repository);
});

afterEach(function () {
    Mockery::close();
});

test('create persists the upload and attaches the media', function () {
    $dto = new UploadDTO(UploadMockData::imageFile('avatar.jpg'));

    $upload = new Upload(['uuid' => 'uuid-1', 'type' => 'image']);

    $media = Mockery::mock(Media::class)->makePartial();
    $media->forceFill([
        'id' => 5,
        'name' => 'avatar',
        'file_name' => 'avatar.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1234,
    ]);
    $media->shouldReceive('getUrl')->once()->andReturn('http://localhost/media/5');

    $this->repository->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => ($data['type'] ?? null) === UploadType::IMAGE))
        ->andReturn($upload);

    $this->repository->shouldReceive('attachMedia')->once()->andReturn($media);
    $this->repository->shouldReceive('update')->once()->andReturn($upload);

    expect($this->service->create($dto))->toBe($upload);
});

test('delete removes the upload through the repository', function () {
    $upload = Upload::factory()->create();

    $this->repository->shouldReceive('delete')->once()->with($upload)->andReturnTrue();

    expect($this->service->delete($upload))->toBeTrue();
});

test('paginate delegates to the repository with the filter page size', function () {
    $filters = new UploadFilterDTO(perPage: 25);
    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $this->repository->shouldReceive('paginate')
        ->once()
        ->with($filters, 25)
        ->andReturn($paginator);

    expect($this->service->paginate($filters))->toBe($paginator);
});
