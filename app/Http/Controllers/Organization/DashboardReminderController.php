<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CreateProfessionalDashboardReminderAction;
use App\Actions\Organization\DeleteProfessionalDashboardReminderAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\DestroyDashboardReminderRequest;
use App\Http\Requests\Organization\StoreDashboardReminderRequest;
use App\Models\Professional;
use App\Models\ProfessionalDashboardReminder;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Lembretes tipo post-it no dashboard do profissional — nunca aceita
 * {professional} na URL, sempre resolvido a partir do usuário autenticado
 * (mesmo padrão de MyAppointmentRequestsController).
 */
class DashboardReminderController extends Controller
{
    public function store(
        StoreDashboardReminderRequest $request,
        TenantContext $tenant,
        CreateProfessionalDashboardReminderAction $action,
    ): RedirectResponse {
        $organization = $tenant->organization();
        abort_if($organization === null, 404);
        $professional = $this->resolveOwnProfessional($organization->id);

        $action->handle($organization, $professional, $request->validated());

        return back();
    }

    public function destroy(
        DestroyDashboardReminderRequest $request,
        ProfessionalDashboardReminder $reminder,
        DeleteProfessionalDashboardReminderAction $action,
    ): RedirectResponse {
        $action->handle($reminder);

        return back();
    }

    private function resolveOwnProfessional(string $organizationId): Professional
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->professionals()
            ->where('organization_id', $organizationId)
            ->where('status', RecordStatus::Active)
            ->firstOrFail();
    }
}
