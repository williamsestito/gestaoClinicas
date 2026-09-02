<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProfessionalServiceAction;
use App\Actions\Organization\AssignServiceToProfessionalAction;
use App\Actions\Organization\DeactivateProfessionalServiceAction;
use App\Actions\Organization\RemoveProfessionalServiceAction;
use App\Actions\Organization\RestoreProfessionalServiceAction;
use App\Actions\Organization\UpdateProfessionalServiceAssignmentAction;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AssignProfessionalServiceRequest;
use App\Http\Requests\Organization\UpdateProfessionalServiceAssignmentRequest;
use App\Models\Professional;
use App\Models\ProfessionalService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProfessionalServiceController extends Controller
{
    public function store(AssignProfessionalServiceRequest $request, Professional $professional, AssignServiceToProfessionalAction $action): RedirectResponse
    {
        $link = $action->handle($professional, $request->attributesForAction());

        $message = $link->status === RecordStatus::Inactive
            ? 'Serviço vinculado ao profissional, mas está inativo por não haver unidade compatível.'
            : 'Serviço vinculado ao profissional com sucesso.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function update(UpdateProfessionalServiceAssignmentRequest $request, Professional $professional, ProfessionalService $professionalService, UpdateProfessionalServiceAssignmentAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalService);
        $this->authorize('manageServices', $professional);

        $action->handle($professionalService, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo atualizado com sucesso.']);

        return back();
    }

    public function activate(Professional $professional, ProfessionalService $professionalService, ActivateProfessionalServiceAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalService);
        $this->authorize('manageServices', $professional);

        $action->handle($professionalService);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo ativado com sucesso.']);

        return back();
    }

    public function deactivate(Professional $professional, ProfessionalService $professionalService, DeactivateProfessionalServiceAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalService);
        $this->authorize('manageServices', $professional);

        $action->handle($professionalService);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo inativado com sucesso.']);

        return back();
    }

    public function destroy(Professional $professional, ProfessionalService $professionalService, RemoveProfessionalServiceAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalService);
        $this->authorize('manageServices', $professional);

        $action->handle($professionalService);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo removido. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(Professional $professional, string $professionalService, RestoreProfessionalServiceAction $action): RedirectResponse
    {
        $link = ProfessionalService::withTrashed()->findOrFail($professionalService);
        $this->authorizeBelongsToProfessional($professional, $link);
        $this->authorize('manageServices', $professional);

        $action->handle($link);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo restaurado com sucesso.']);

        return back();
    }

    private function authorizeBelongsToProfessional(Professional $professional, ProfessionalService $link): void
    {
        if ($link->professional_id !== $professional->id) {
            abort(404);
        }
    }
}
