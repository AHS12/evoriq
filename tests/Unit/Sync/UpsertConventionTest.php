<?php

use App\Models\ClockifyWorkspace;
use App\Models\Concerns\BelongsToOrganization;
use App\Repositories\Sync\AbstractSyncUpsertRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Schema::dropIfExists('upsert_test_entities');

    Schema::create('upsert_test_entities', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('organization_id')->nullable();
        $table->unsignedBigInteger('workspace_id')->nullable();
        $table->string('clockify_id');
        $table->string('name')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamp('synced_at')->nullable();
        $table->timestamps();

        $table->unique(['organization_id', 'workspace_id', 'clockify_id']);
    });
});

test('applying the same page twice creates no duplicates and reports counts', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $repository = new UpsertTestRepository;

    $rows = [
        ['clockify_id' => 'e1', 'name' => 'A'],
        ['clockify_id' => 'e2', 'name' => 'B'],
    ];

    $first = $repository->upsertMany($workspace, $rows);

    expect($first->created)->toBe(2)
        ->and($first->updated)->toBe(0)
        ->and($first->unchanged)->toBe(0)
        ->and($first->processed())->toBe(2);

    $second = $repository->upsertMany($workspace, $rows);

    expect($second->created)->toBe(0)
        ->and($second->updated)->toBe(0)
        ->and($second->unchanged)->toBe(2);

    expect(UpsertTestEntity::query()->count())->toBe(2);

    $third = $repository->upsertMany($workspace, [['clockify_id' => 'e1', 'name' => 'A2']]);

    expect($third->updated)->toBe(1)
        ->and(UpsertTestEntity::query()->where('clockify_id', 'e1')->value('name'))->toBe('A2');
});

test('the composite key prevents cross-workspace overwrite', function () {
    $first = ClockifyWorkspace::factory()->create();
    $second = ClockifyWorkspace::factory()->create();
    $repository = new UpsertTestRepository;

    $repository->upsertMany($first, [['clockify_id' => 'e1', 'name' => 'A']]);
    $repository->upsertMany($second, [['clockify_id' => 'e1', 'name' => 'B']]);

    expect(UpsertTestEntity::query()->count())->toBe(2)
        ->and(UpsertTestEntity::query()->where('workspace_id', $first->id)->value('name'))->toBe('A')
        ->and(UpsertTestEntity::query()->where('workspace_id', $second->id)->value('name'))->toBe('B');
});

test('upsertByClockifyId persists and returns the model', function () {
    $workspace = ClockifyWorkspace::factory()->create();
    $repository = new UpsertTestRepository;

    $model = $repository->upsertByClockifyId($workspace, ['clockify_id' => 'e1', 'name' => 'A']);

    expect($model)->toBeInstanceOf(UpsertTestEntity::class)
        ->and($model->clockify_id)->toBe('e1')
        ->and($model->workspace_id)->toBe($workspace->id)
        ->and($model->organization_id)->toBe($workspace->organization_id);
});

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string|null $name
 * @property bool $active
 */
class UpsertTestEntity extends Model
{
    use BelongsToOrganization;

    protected $table = 'upsert_test_entities';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}

class UpsertTestRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return UpsertTestEntity::class;
    }
}
