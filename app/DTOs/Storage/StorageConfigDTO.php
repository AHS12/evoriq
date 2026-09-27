<?php

namespace App\DTOs\Storage;

use App\Http\Requests\Setting\UpdateStorageRequest;
use Illuminate\Http\Request;

final readonly class StorageConfigDTO
{
    public function __construct(
        public bool $enabled,
        public string $provider,
        public string $accessKeyId,
        public ?string $secretAccessKey,
        public string $region,
        public string $bucket,
        public ?string $endpoint,
        public bool $usePathStyle,
    ) {}

    public static function fromRequest(UpdateStorageRequest|Request $request): self
    {
        return new self(
            enabled: $request->boolean('enabled'),
            provider: (string) $request->input('provider', 's3'),
            accessKeyId: (string) $request->input('access_key_id', ''),
            secretAccessKey: filled($request->input('secret_access_key'))
                ? (string) $request->input('secret_access_key')
                : null,
            region: (string) $request->input('region', ''),
            bucket: (string) $request->input('bucket', ''),
            endpoint: filled($request->input('endpoint')) ? (string) $request->input('endpoint') : null,
            usePathStyle: $request->boolean('use_path_style'),
        );
    }
}
