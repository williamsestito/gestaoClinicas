<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProfessionalTimeBlockAction;
use App\Actions\Organization\CreateProfessionalTimeBlockAction;
use App\Actions\Organization\DeactivateProfessionalTimeBlockAction;
use App\Actions\Organization\DeleteProfessionalTimeBlockAction;
use App\Actions\Organization\RestoreProfessionalTimeBlockAction;
use App\Actions\Organization\UpdateProfessionalTimeBlockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateProfessionalTimeBlockRequest;
use App\Http\Requests\Organization\UpdateProfessionalTimeBlockRequest;
use App\Models\Professional;
use App\Models\ProfessionalTimeBlock;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProfessionalTimeBlockController extends Controller
{
    public function store(CreateProfessionalTimeBlockRequest $request, Professional $professional, CreateProfessionalTimeBlockAction $action): RedirectResponse
    {
        $action->handle($professional, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ausência cadastrada com sucesso.']);

        return back();
    }

    public function update(UpdateProfessionalTimeBlockRequest $request, Professional $professional, ProfessionalTimeBlock $timeBlock, UpdateProfessionalTimeBlockAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $timeBlock);

        $action->handle($timeBlock, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueio alterado com sucesso.']);

        return back();
    }

    public function activate(Professional $professional, ProfessionalTimeBlock $timeBlock, ActivateProfessionalTimeBlockAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $timeBlock);
        $this->authorize('manageTimeBlocks', [$professional, $timeBlock->unit]);

        $action->handle($timeBlock);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueio ativado com sucesso.']);

        return back();
    }

    public function deactivate(Professional $professional, ProfessionalTimeBlock $timeBlock, DeactivateProfessionalTimeBlockAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $timeBlock);
        $this->authorize('manageTimeBlocks', [$professional, $timeBlock->unit]);

        $action->handle($timeBlock);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueio inativado com sucesso.']);

        return back();
    }

    public function destroy(Professional $professional, ProfessionalTimeBlock $timeBlock, DeleteProfessionalTimeBlockAction $action): RedirectResponse
    {
        $this->authorizeBelongsToProfessional($professional, $timeBlock);
        $this->authorize('manageTimeBlocks', [$professional, $timeBlock->unit]);

        $action->handle($timeBlock);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'O bloqueio foi excluído. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(Professional $professional, string $timeBlock, RestoreProfessionalTimeBlockAction $action): RedirectResponse
    {
        $entity = ProfessionalTimeBlock::withTrashed()->findOrFail($timeBlock);
        $this->authorizeBelongsToProfessional($professional, $entity);
        $this->authorize('manageTimeBlocks', [$professional, $entity->unit]);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueio restaurado com sucesso.']);

        return back();
    }

    private function authorizeBelongsToProfessional(Professional $professional, ProfessionalTimeBlock $timeBlock): void
    {
        if ($timeBlock->professional_id !== $professional->id) {
            abort(404);
        }
    }
}
