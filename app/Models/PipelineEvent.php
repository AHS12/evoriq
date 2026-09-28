<?php

namespace App\Models;

use App\Enums\PipelineEventLevel;
use App\Enums\PipelineEventType;
use App\Enums\PipelineRunType;
use App\Models\Concerns\BelongsToOrganization;
use App\Services\Pipeline\PipelineEventRecorder;
use Database\Factories\PipelineEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An immutable, append-only entry in a background run's event stream.
 *
 * Events are written by {@see PipelineEventRecorder}
 * and consumed by the pipeline timeline views (PIPE-05/06).
 *
 * @property int $id
 * @property int|string|null $organization_id
 * @property PipelineRunType $run_type
 * @property string $run_id
 * @property int $sequence
 * @property PipelineEventType $type
 * @property PipelineEventLevel $level
 * @property string|null $stage
 * @property string|null $message
 * @property array<string, mixed>|null $context
 * @property array<string, mixed>|null $progress
 * @property int $attempt
 * @property int|null $duration_ms
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property-read Organization|null $organization
 */
#[Fillable([
    'organization_id', 'run_type', 'run_id', 'sequence', 'type', 'level',
    'stage', 'message', 'context', 'progress', 'attempt', 'duration_ms',
    'occurred_at', 'created_at',
])]
class PipelineEvent extends Model
{
    /**
     * @use HasFactory<PipelineEventFactory>
     * @use BelongsToOrganization<PipelineEvent>
     */
    use BelongsToOrganization, HasFactory;

    /**
     * The stream is append-only — rows are never updated.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'run_type' => PipelineRunType::class,
            'type' => PipelineEventType::class,
            'level' => PipelineEventLevel::class,
            'context' => 'array',
            'progress' => 'array',
            'sequence' => 'integer',
            'attempt' => 'integer',
            'duration_ms' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Scope the stream to a single run.
     *
     * @param  Builder<PipelineEvent>  $query
     * @return Builder<PipelineEvent>
     */
    public function scopeForRun(Builder $query, PipelineRunType $type, string|int $runId): Builder
    {
        return $query
            ->where('run_type', $type->value)
            ->where('run_id', (string) $runId);
    }

    /**
     * Scope the stream to a severity level.
     *
     * @param  Builder<PipelineEvent>  $query
     * @return Builder<PipelineEvent>
     */
    public function scopeLevel(Builder $query, PipelineEventLevel $level): Builder
    {
        return $query->where('level', $level->value);
    }

    /**
     * Scope the stream to a single event type.
     *
     * @param  Builder<PipelineEvent>  $query
     * @return Builder<PipelineEvent>
     */
    public function scopeOfType(Builder $query, PipelineEventType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * Order the stream chronologically, oldest first.
     *
     * @param  Builder<PipelineEvent>  $query
     * @return Builder<PipelineEvent>
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('sequence');
    }

    public function isTerminal(): bool
    {
        return $this->type->isTerminal();
    }
}
