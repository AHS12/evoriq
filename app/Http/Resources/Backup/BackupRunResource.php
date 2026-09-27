<?php

namespace App\Http\Resources\Backup;

use App\Models\BackupRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BackupRun
 */
class BackupRunResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'trigger' => $this->trigger->value,
            'trigger_label' => $this->trigger->label(),
            'disk' => $this->disk,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'downloadable' => $this->isDownloadable(),
            'exit_code' => $this->exit_code,
            'error_message' => $this->error_message,
            'output' => $this->output,
            'finished' => $this->isFinished(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user !== null ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
        ];
    }
}
