<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ChangeLegalEntityStatusAction;
use App\Actions\Organization\CreateLegalEntityAction;
use App\Actions\Organization\DeleteLegalEntityAction;
use App\Actions\Organization\RestoreLegalEntityAction;
use App\Actions\Organization\SetPrimaryLegalEntityAction;
use App\Actions\Organization\UpdateLegalEntityAction;
use App\Data\Organization\AddressData;
use App\Enums\LegalEntityType;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateLegalEntityRequest;
use App\Http\Requests\Organization\UpdateLegalEntityRequest;
use App\Models\LegalEntity;
use App\Support\Documents\BrazilianState;
use App\Support\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class LegalEntityController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $legalEntities = $tenant->organization()
            ->legalEntities()
            ->with('address')
            ->withTrashed()
            ->orderByDesc('is_primary')
            ->orderBy('legal_name')
            ->get()
            ->map(fn (LegalEntity $legalEntity) => [
                ...$legalEntity->only([
                    'id', 'organization_id', 'type', 'legal_name', 'trade_name',
                    'email', 'phone', 'is_primary', 'status', 'deleted_at',
                ]),
                'type' => $legalEntity->type->value,
                'status' => $legalEntity->status->value,
                'document' => self::maskDocument($legalEntity),
                'address' => $legalEntity->address ? [
                    'city' => $legalEntity->address->city,
                    'state' => $legalEntity->address->state,
                ] : null,
            ]);

        return Inertia::render('settings/legal-entities/Index', [
            'legalEntities' => $legalEntities,
            'legalEntityTypes' => array_map(
                fn (LegalEntityType $type) => ['value' => $type->value, 'label' => $type->label()],
                LegalEntityType::cases(),
            ),
            'states' => BrazilianState::codes(),
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [LegalEntity::class, $tenant->organization()]);

        return Inertia::render('settings/legal-entities/Create', [
            'legalEntityTypes' => array_map(
                fn (LegalEntityType $type) => ['value' => $type->value, 'label' => $type->label()],
                LegalEntityType::cases(),
            ),
            'states' => BrazilianState::codes(),
        ]);
    }

    public function store(CreateLegalEntityRequest $request, CreateLegalEntityAction $action, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validated();

        $action->handle(
            organization: $tenant->organization(),
            type: LegalEntityType::from($data['type']),
            document: Document::onlyDigits($data['document']),
            legalName: $data['legal_name'],
            tradeName: $data['trade_name'] ?? null,
            address: AddressData::fromArray($data['address']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entidade legal cadastrada com sucesso.']);

        return to_route('settings.legal-entities.index');
    }

    public function edit(LegalEntity $legalEntity): Response
    {
        $this->authorize('view', $legalEntity);

        $legalEntity->load('address');

        return Inertia::render('settings/legal-entities/Edit', [
            'legalEntity' => [
                ...$legalEntity->only([
                    'id', 'organization_id', 'type', 'legal_name', 'trade_name',
                    'state_registration', 'municipal_registration', 'email', 'phone',
                    'is_primary', 'status', 'deleted_at',
                ]),
                'type' => $legalEntity->type->value,
                'status' => $legalEntity->status->value,
                'document' => self::maskDocument($legalEntity),
                'address' => $legalEntity->address,
            ],
        ]);
    }

    private static function maskDocument(LegalEntity $legalEntity): string
    {
        try {
            return Document::fromType($legalEntity->type, $legalEntity->document)->masked();
        } catch (InvalidArgumentException) {
            return '****';
        }
    }

    public function update(UpdateLegalEntityRequest $request, LegalEntity $legalEntity, UpdateLegalEntityAction $action): RedirectResponse
    {
        $action->handle($legalEntity, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entidade legal atualizada com sucesso.']);

        return to_route('settings.legal-entities.index');
    }

    public function updateStatus(Request $request, LegalEntity $legalEntity, ChangeLegalEntityStatusAction $action): RedirectResponse
    {
        $this->authorize('update', $legalEntity);

        $status = $request->boolean('active') ? RecordStatus::Active : RecordStatus::Inactive;
        $action->handle($legalEntity, $status);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $status === RecordStatus::Active
                ? 'Entidade legal ativada com sucesso.'
                : 'Entidade legal inativada com sucesso.',
        ]);

        return back();
    }

    public function makePrimary(LegalEntity $legalEntity, SetPrimaryLegalEntityAction $action): RedirectResponse
    {
        $this->authorize('setPrimary', $legalEntity);

        $action->handle($legalEntity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entidade legal definida como principal.']);

        return back();
    }

    public function destroy(LegalEntity $legalEntity, DeleteLegalEntityAction $action): RedirectResponse
    {
        $this->authorize('delete', $legalEntity);

        $action->handle($legalEntity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entidade legal excluída com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(int|string $legalEntity, TenantContext $tenant, RestoreLegalEntityAction $action): RedirectResponse
    {
        $entity = LegalEntity::withTrashed()->findOrFail($legalEntity);

        if (! $tenant->organization() || $entity->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $entity);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entidade legal restaurada com sucesso.']);

        return back();
    }
}
