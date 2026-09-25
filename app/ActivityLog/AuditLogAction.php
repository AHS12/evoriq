<?php

namespace App\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Models\Activity;

/**
 * Central audit write pipeline: enriches every activity with the current
 * request/job context and strips sensitive values before anything reaches
 * the database.
 */
final class AuditLogAction extends LogActivityAction
{
    /** Depth guard so recursive redaction never runs away on circular arrays. */
    private const MAX_REDACTION_DEPTH = 8;

    public function __construct(private readonly AuditContext $context)
    {
        //
    }

    protected function transformChanges(Model $activity): void
    {
        $this->mergeContext($activity);
        $this->redact($activity);
    }

    private function mergeContext(Model $activity): void
    {
        $context = $this->context->context();

        if ($context === [] || ! $activity instanceof Activity) {
            return;
        }

        $properties = $activity->properties;

        $activity->properties = collect($context)
            ->merge($properties instanceof Collection ? $properties->toArray() : (array) $properties);
    }

    private function redact(Model $activity): void
    {
        if (! $activity instanceof Activity) {
            return;
        }

        if ($activity->attribute_changes !== null) {
            $activity->attribute_changes = collect(
                $this->redactArray($activity->attribute_changes->toArray())
            );
        }

        if ($activity->properties !== null) {
            $activity->properties = collect(
                $this->redactArray($activity->properties->toArray())
            );
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redactArray(array $data, int $depth = 0): array
    {
        if ($depth >= self::MAX_REDACTION_DEPTH) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactArray($value, $depth + 1);
            }
        }

        return $data;
    }

    private function isSensitive(string $key): bool
    {
        foreach ((array) config('audit.redacted_attributes', []) as $pattern) {
            if (Str::is($pattern, $key)) {
                return true;
            }
        }

        return false;
    }
}
