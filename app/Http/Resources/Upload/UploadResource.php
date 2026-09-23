<?php

namespace App\Http\Resources\Upload;

use App\Enums\MediaCollection;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Upload
 */
class UploadResource extends JsonResource
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
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => $this->url ?: ($this->getFirstMediaUrl(MediaCollection::UPLOAD->value) ?: null),
            'thumb_url' => $this->getFirstMediaUrl(MediaCollection::UPLOAD->value, 'thumb') ?: null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
