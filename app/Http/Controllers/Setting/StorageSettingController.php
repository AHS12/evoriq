<?php

namespace App\Http\Controllers\Setting;

use App\DTOs\Storage\StorageConfigDTO;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateStorageRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Storage\StorageConfigurator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class StorageSettingController extends Controller
{
    public function __construct(
        protected StorageConfigurator $configurator,
        protected AuditLogService $audit,
    ) {}

    /**
     * The remote storage settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('admin/settings/storage', [
            'storage' => $this->configurator->current(),
            'configured' => $this->configurator->isConfigured(),
        ]);
    }

    /**
     * Persist the remote storage credentials to the environment file.
     */
    public function update(UpdateStorageRequest $request): RedirectResponse
    {
        try {
            $this->configurator->write(StorageConfigDTO::fromRequest($request));
        } catch (RuntimeException $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Could not save the remote storage settings: :error', ['error' => $e->getMessage()]),
            ]);

            return back();
        }

        $this->audit->record(
            AuditEvent::STORAGE_UPDATED,
            properties: ['keys' => 'AWS_*'],
            actor: $request->user(),
            channel: AuditLogName::SETTINGS,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Remote storage settings saved.'),
        ]);

        return to_route('admin.settings.storage.edit');
    }

    /**
     * Probe the remote disk with the currently configured credentials.
     */
    public function test(Request $request): RedirectResponse
    {
        try {
            $result = $this->configurator->test();
        } catch (Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}
