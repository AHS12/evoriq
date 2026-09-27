<?php

namespace App\Http\Controllers\Backup;

use App\DTOs\Backup\BackupRunFilterDTO;
use App\Enums\BackupRunType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backup\BackupRunResource;
use App\Models\BackupRun;
use App\Repositories\Contracts\BackupRunRepositoryInterface;
use App\Services\Backup\BackupScheduleService;
use App\Services\Backup\BackupService;
use App\Services\Setting\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupRunRepositoryInterface $runs,
        protected BackupScheduleService $schedule,
        protected BackupService $service,
        protected SettingService $settings,
    ) {}

    /**
     * The backup settings page: status, settings form and run history.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BackupRun::class);

        $groups = collect($this->settings->groups())->keyBy('key');

        $filters = BackupRunFilterDTO::fromRequest($request);

        return Inertia::render('admin/settings/backup', [
            'group' => $groups->get('backup'),
            'groups' => $this->settings->groups(),
            'runs' => BackupRunResource::collection($this->runs->paginate($filters, $filters->perPage)),
            'status' => $this->status(),
        ]);
    }

    /**
     * Queue a manual backup run.
     */
    public function run(Request $request): RedirectResponse
    {
        Gate::authorize('create', BackupRun::class);

        if ($this->runs->isActive(BackupRunType::BACKUP)) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('A backup is already running.'),
            ]);

            return back();
        }

        $this->service->startManualBackup($request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Backup queued.'),
        ]);

        return back();
    }

    /**
     * Download a completed backup archive.
     */
    public function download(Request $request, BackupRun $backupRun): StreamedResponse
    {
        Gate::authorize('view', $backupRun);

        abort_unless($backupRun->isDownloadable(), 404);

        abort_unless(Storage::disk($backupRun->disk)->exists($backupRun->file_name), 404);

        return Storage::disk($backupRun->disk)->download($backupRun->file_name);
    }

    /**
     * The status summary for the backup page.
     *
     * @return array<string, mixed>
     */
    protected function status(): array
    {
        $latestBackup = $this->runs->latestOfType(BackupRunType::BACKUP, status: null);
        $latestMonitor = $this->runs->latestOfType(BackupRunType::MONITOR);

        return [
            'enabled' => $this->schedule->isEnabled(),
            'frequency' => $this->schedule->frequency(),
            'time' => $this->schedule->time(),
            'uses_remote' => $this->schedule->usesRemote(),
            'remote_configured' => $this->schedule->remoteConfigured(),
            'next_run_at' => $this->schedule->nextRunAt()?->toIso8601String(),
            'storage_used_mb' => $this->storageUsedMb(),
            'storage_max_mb' => $this->schedule->maxStorageMb(),
            'last_backup' => $latestBackup !== null ? BackupRunResource::make($latestBackup)->resolve() : null,
            'health' => $latestMonitor?->status->value,
            'running' => $this->runs->isActive(BackupRunType::BACKUP),
        ];
    }

    /**
     * The bytes currently used by backups on the local destination disk.
     */
    protected function storageUsedMb(): ?float
    {
        return $this->schedule->localDiskUsageMb();
    }
}
