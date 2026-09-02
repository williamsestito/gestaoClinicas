<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProfessionalRegistrationAction;
use App\Actions\Organization\CreateProfessionalRegistrationAction;
use App\Actions\Organization\DeactivateProfessionalRegistrationAction;
use App\Actions\Organization\DeleteProfessionalRegistrationAction;
use App\Actions\Organization\RestoreProfessionalRegistrationAction;
use App\Actions\Organization\SetPrimaryProfessionalRegistrationAction;
use App\Actions\Organization\UpdateProfessionalRegistrationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateProfessionalRegistrationRequest;
use App\Http\Requests\Organization\UpdateProfessionalRegistrationRequest;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProfessionalRegistrationController extends Controller
{
    public function store(CreateProfessionalRegistrationRequest $request, Professional $professional, CreateProfessionalRegistrationAction $action): RedirectResponse
    {
        $action->handle($professional, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro profissional cadastrado com sucesso.']);

        return back();
    }

    public function update(UpdateProfessionalRegistrationRequest $request, Professional $professional, ProfessionalRegistration $professionalRegistration, UpdateProfessionalRegistrationAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalRegistration);

        $action->handle($professionalRegistration, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro profissional alterado com sucesso.']);

        return back();
    }

    public function setPrimary(Professional $professional, ProfessionalRegistration $professionalRegistration, SetPrimaryProfessionalRegistrationAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalRegistration);
        $this->authorize('setPrimary', $professionalRegistration);

        $action->handle($professionalRegistration);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro profissional principal alterado com sucesso.']);

        return back();
    }

    public function activate(Professional $professional, ProfessionalRegistration $professionalRegistration, ActivateProfessionalRegistrationAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalRegistration);
        $this->authorize('activate', $professionalRegistration);

        $action->handle($professionalRegistration);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro profissional ativado com sucesso.']);

        return back();
    }

    public function deactivate(Professional $professional, ProfessionalRegistration $professionalRegistration, DeactivateProfessionalRegistrationAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalRegistration);
        $this->authorize('deactivate', $professionalRegistration);

        $action->handle($professionalRegistration);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro profissional inativado com sucesso.']);

        return back();
    }

    public function destroy(Professional $professional, ProfessionalRegistration $professionalRegistration, DeleteProfessionalRegistrationAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalRegistration);
        $this->authorize('delete', $professionalRegistration);

        $action->handle($professionalRegistration);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro profissional excluído. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(Professional $professional, string $professionalRegistration, RestoreProfessionalRegistrationAction $action): RedirectResponse
    {
        $registration = ProfessionalRegistration::withTrashed()->findOrFail($professionalRegistration);
        $this->authorizeBelongsToProfessional($professional, $registration);
        $this->authorize('restore', $registration);

        $action->handle($registration);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registro profissional restaurado com sucesso.']);

        return back();
    }

    /**
     * Revela o número completo do registro sob demanda — nunca enviado
     * antecipadamente nas props da listagem/edição.
     */
    public function reveal(Professional $professional, ProfessionalRegistration $professionalRegistration): JsonResponse
    {
        $this->authorizeBelongsToProfessional($professional, $professionalRegistration);
        $this->authorize('viewSensitive', $professionalRegistration);

        return response()->json(['registration_number' => $professionalRegistration->registration_number]);
    }

    private function authorizeBelongsToProfessional(Professional $professional, ProfessionalRegistration $registration): void
    {
        if ($registration->professional_id !== $professional->id) {
            abort(404);
        }
    }
}
