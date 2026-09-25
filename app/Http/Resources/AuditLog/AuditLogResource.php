<?php

namespace App\Http\Resources\AuditLog;

use App\Models\AuditActivity;
use App\Models\DataProcessingJob;
use App\Models\Role;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditActivity
 */
class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'event' => $this->event,
            'channel' => $this->log_name,
            'subject' => $this->whenLoaded('subject', fn (): ?array => $this->subjectPayload()),
            'causer' => $this->whenLoaded('causer', fn (): ?array => $this->causerPayload()),
            'properties' => $this->presentableProperties(),
            'attribute_changes' => $this->attribute_changes?->toArray(),
            'correlation_id' => $this->getProperty('correlation_id'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subjectPayload(): ?array
    {
        $subject = $this->subject;

        if ($subject === null) {
            return null;
        }

        return [
            'type' => $subject->getMorphClass(),
            'id' => $subject->getKey(),
            'label' => $this->subjectLabel($subject),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function causerPayload(): ?array
    {
        $causer = $this->causer;

        if (! $causer instanceof User) {
            return null;
        }

        return [
            'id' => $causer->id,
            'name' => $causer->name,
            'email' => $causer->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentableProperties(): array
    {
        $properties = $this->properties?->toArray() ?? [];

        unset($properties['url'], $properties['method']);

        return $properties;
    }

    private function subjectLabel(mixed $subject): string
    {
        if ($subject instanceof User) {
            return $subject->email;
        }

        if ($subject instanceof Role) {
            return $subject->name;
        }

        if ($subject instanceof Upload) {
            return $subject->name ?? (string) $subject->file_name;
        }

        if ($subject instanceof DataProcessingJob) {
            return $subject->displayName();
        }

        return (string) ($subject->getKey() ?? '');
    }
}
