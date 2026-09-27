<?php

namespace App\Services\Storage;

use App\DTOs\Storage\StorageConfigDTO;
use App\Services\Setup\EnvironmentWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Configures the remote (S3-compatible) object storage disk by writing the
 * AWS_* keys to the environment file, mirroring the first-run wizard's
 * EnvironmentWriter flow (write → config:clear → runtime reload).
 *
 * Setup-adjacent infrastructure: deliberately outside the module
 * Service–Repository layers, like the Setup services.
 */
class StorageConfigurator
{
    /**
     * Environment keys managed by this configurator.
     */
    private const KEYS = [
        'enabled' => 'AWS_ENABLED',
        'access_key_id' => 'AWS_ACCESS_KEY_ID',
        'region' => 'AWS_DEFAULT_REGION',
        'bucket' => 'AWS_BUCKET',
        'endpoint' => 'AWS_ENDPOINT',
        'use_path_style' => 'AWS_USE_PATH_STYLE_ENDPOINT',
    ];

    public function __construct(
        protected EnvironmentWriter $writer,
    ) {}

    /**
     * Current configuration for the settings form. The secret is never
     * returned; only whether one is stored.
     *
     * @return array{enabled: bool, provider: string, access_key_id: string, has_secret: bool, region: string, bucket: string, endpoint: string|null, use_path_style: bool}
     */
    public function current(): array
    {
        $values = $this->writer->read();

        return [
            'enabled' => ($values['AWS_ENABLED'] ?? 'false') === 'true',
            'provider' => $this->detectProvider($values),
            'access_key_id' => $values['AWS_ACCESS_KEY_ID'] ?? '',
            'has_secret' => isset($values['AWS_SECRET_ACCESS_KEY']) && $values['AWS_SECRET_ACCESS_KEY'] !== '',
            'region' => $values['AWS_DEFAULT_REGION'] ?? '',
            'bucket' => $values['AWS_BUCKET'] ?? '',
            'endpoint' => $values['AWS_ENDPOINT'] ?? null,
            'use_path_style' => ($values['AWS_USE_PATH_STYLE_ENDPOINT'] ?? 'false') === 'true',
        ];
    }

    /**
     * Whether remote storage is enabled and fully configured.
     */
    public function isConfigured(): bool
    {
        $values = $this->writer->read();

        return ($values['AWS_ENABLED'] ?? 'false') === 'true'
            && filled($values['AWS_ACCESS_KEY_ID'] ?? null)
            && filled($values['AWS_SECRET_ACCESS_KEY'] ?? null)
            && filled($values['AWS_BUCKET'] ?? null);
    }

    /**
     * Persist the configuration to the environment file and reload it.
     */
    public function write(StorageConfigDTO $dto): void
    {
        $values = [
            self::KEYS['enabled'] => $dto->enabled,
            self::KEYS['access_key_id'] => $dto->accessKeyId,
            self::KEYS['region'] => $dto->region,
            self::KEYS['bucket'] => $dto->bucket,
            self::KEYS['endpoint'] => $dto->endpoint ?? '',
            self::KEYS['use_path_style'] => $dto->usePathStyle,
        ];

        // Only overwrite the secret when a new one was supplied.
        if ($dto->secretAccessKey !== null) {
            $values['AWS_SECRET_ACCESS_KEY'] = $dto->secretAccessKey;
        }

        $this->writer->set($values);

        $this->reload();
    }

    /**
     * Probe the remote disk with a write/read/delete round-trip.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(): array
    {
        $probe = '.backup-probe-'.bin2hex(random_bytes(4));

        try {
            $disk = Storage::disk('s3');

            if (! $disk->put($probe, 'ok')) {
                return ['ok' => false, 'message' => __('Could not write the probe file to the remote disk.')];
            }

            if ($disk->get($probe) !== 'ok') {
                return ['ok' => false, 'message' => __('The probe file could not be read back from the remote disk.')];
            }

            $disk->delete($probe);

            return ['ok' => true, 'message' => __('Successfully connected to the remote storage bucket.')];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => __('Connection failed: :error', ['error' => $e->getMessage()])];
        }
    }

    /**
     * Reload the written values into the running process and nudge long-lived
     * workers (backup jobs run on the queue with the old config in memory).
     */
    protected function reload(): void
    {
        try {
            Artisan::call('config:clear');
        } catch (Throwable) {
            // The runtime config is updated manually below regardless.
        }

        try {
            Artisan::call('queue:restart');
        } catch (Throwable) {
            // Not critical: workers pick the config up on their next natural restart.
        }

        $values = $this->writer->read();

        config([
            'filesystems.disks.s3.key' => $values['AWS_ACCESS_KEY_ID'] ?? null,
            'filesystems.disks.s3.secret' => $values['AWS_SECRET_ACCESS_KEY'] ?? null,
            'filesystems.disks.s3.region' => $values['AWS_DEFAULT_REGION'] ?? null,
            'filesystems.disks.s3.bucket' => $values['AWS_BUCKET'] ?? null,
            'filesystems.disks.s3.endpoint' => $values['AWS_ENDPOINT'] ?? null,
            'filesystems.disks.s3.use_path_style_endpoint' => ($values['AWS_USE_PATH_STYLE_ENDPOINT'] ?? 'false') === 'true',
        ]);
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function detectProvider(array $values): string
    {
        $endpoint = $values['AWS_ENDPOINT'] ?? '';

        if (str_contains($endpoint, 'r2.cloudflarestorage.com')) {
            return 'r2';
        }

        return filled($endpoint) ? 'custom' : 's3';
    }
}
