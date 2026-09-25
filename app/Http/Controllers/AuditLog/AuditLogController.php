<?php

namespace App\Http\Controllers\AuditLog;

use App\DTOs\AuditLog\AuditLogFilterDTO;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLog\AuditLogResource;
use App\Models\AuditActivity;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Support\DataProcessingOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditActivity::class);

        $user = $request->user();
        $canViewAll = $user instanceof User && $user->hasPermissionTo('audit.view.all');

        $filters = AuditLogFilterDTO::fromRequest($request);

        if (! $canViewAll && $user instanceof User) {
            $filters = $filters->scopedToCauser($user->id);
        }

        $logs = $this->audit->paginate($filters, $request->string('cursor')->toString() ?: null);

        return Inertia::render('audit-logs/index', [
            'logs' => AuditLogResource::collection($logs),
            'filters' => $filters->toArray(),
            'channels' => array_map(
                fn (AuditLogName $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
                AuditLogName::cases(),
            ),
            'events' => array_map(
                fn (AuditEvent $event): array => ['value' => $event->value, 'label' => $event->label()],
                AuditEvent::cases(),
            ),
            'canViewAll' => $canViewAll,
            'canManage' => $user !== null && $user->can('prune', AuditActivity::class),
            'canExport' => $user !== null && ($user->hasPermissionTo('audit.export') || $user->can('export.create')),
            'processingOptions' => DataProcessingOptions::make(),
        ]);
    }

    public function show(AuditActivity $auditLog): AuditLogResource
    {
        Gate::authorize('view', $auditLog);

        return AuditLogResource::make($auditLog->loadMissing(['causer', 'subject']));
    }

    public function prune(): RedirectResponse
    {
        Gate::authorize('prune', AuditActivity::class);

        $this->audit->prune();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Audit log pruned.')]);

        return back();
    }
}
