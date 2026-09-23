<?php

namespace App\Enums;

enum UploadType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case DOCUMENT = 'document';
    case OTHER = 'other';

    public static function fromMimeType(string $mimeType): self
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => self::IMAGE,
            str_starts_with($mimeType, 'video/') => self::VIDEO,
            $mimeType === 'application/pdf' => self::DOCUMENT,
            default => self::OTHER,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::VIDEO => 'Video',
            self::DOCUMENT => 'Document',
            self::OTHER => 'Other',
        };
    }
}
