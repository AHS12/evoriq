<?php

namespace App\DTOs\Upload;

use App\Http\Requests\Upload\StoreUploadRequest;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final readonly class UploadDTO
{
    public function __construct(
        public UploadedFile $file,
    ) {}

    public static function fromRequest(StoreUploadRequest $request): self
    {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw new RuntimeException('A file is required to create an upload.');
        }

        return new self(file: $file);
    }
}
