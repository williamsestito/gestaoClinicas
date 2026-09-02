<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $organization = $tenant->organization();

        $this->authorize('viewAny', [AuditLog::class, $organization]);

        $logs = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->with(['actor:id,name', 'unit:id,name'])
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('entity'), fn ($query) => $query->where('auditable_type', $request->string('entity')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('actor', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->actor ? $log->actor->name : 'Sistema',
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'entity' => class_basename((string) $log->auditable_type),
                'unit' => $log->unit?->name,
                'ip_address' => $log->ip_address,
                'before' => $log->before_data,
                'after' => $log->after_data,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'entity', 'search', 'from', 'to']),
            'actionOptions' => collect(AuditAction::cases())
                ->map(fn (AuditAction $action) => ['value' => $action->value, 'label' => $action->label()]),
        ]);
    }
}
