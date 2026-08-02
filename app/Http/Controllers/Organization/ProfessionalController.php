<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProfessionalAction;
use App\Actions\Organization\CreateProfessionalAction;
use App\Actions\Organization\DeactivateProfessionalAction;
use App\Actions\Organization\DeleteProfessionalAction;
use App\Actions\Organization\DestroyProfessionalPhotoAction;
use App\Actions\Organization\LinkProfessionalUserAction;
use App\Actions\Organization\RestoreProfessionalAction;
use App\Actions\Organization\UnlinkProfessionalUserAction;
use App\Actions\Organization\UpdateProfessionalAction;
use App\Actions\Organization\UpdateProfessionalPhotoAction;
use App\Enums\LegalEntityType;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateProfessionalRequest;
use App\Http\Requests\Organization\LinkProfessionalUserRequest;
use App\Http\Requests\Organization\UpdateProfessionalPhotoRequest;
use App\Http\Requests\Organization\UpdateProfessionalRequest;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Models\ProfessionalRegistration;
use App\Models\ProfessionalService;
use App\Models\ProfessionalSpecialty;
use App\Models\ProfessionalTimeBlock;
use App\Models\ProfessionalUnit;
use App\Models\ProfessionalWorkingHour;
use App\Queries\ProfessionalListQuery;
use App\Services\Professionals\ProfessionalOperationalStatusResolver;
use App\Support\Availability\WorkingHourOpeningHoursGuard;
use App\Support\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ProfessionalController extends Controller
{
    public function index(TenantContext $tenant, ProfessionalListQuery $professionalListQuery): Response
    {
        $this->authorize('viewAny', [Professional::class, $tenant->organization()]);

        $professionals = $professionalListQuery->forOrganization($tenant->organization())
            ->map(fn (array $row) => Arr::except(array_merge($row, [
                'phone' => self::maskPhone($row['phone']),
                'document' => self::maskDocument($row['document']),
                'photo_url' => $row['photo_path'] ? Storage::disk('public')->url($row['photo_path']) : null,
            ]), 'photo_path'));

        return Inertia::render('settings/professionals/Index', [
            'professionals' => $professionals,
            'eligibleUsers' => $this->eligibleUsers($tenant),
            'units' => $this->activeUnitOptions($tenant),
            'specialties' => $this->activeSpecialtyOptions($tenant),
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [Professional::class, $tenant->organization()]);

        return Inertia::render('settings/professionals/Create', [
            'eligibleUsers' => $this->eligibleUsers($tenant),
        ]);
    }

    public function store(CreateProfessionalRequest $request, CreateProfessionalAction $action, TenantContext $tenant): RedirectResponse
    {
        $action->handle($tenant->organization(), $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional cadastrado com sucesso.']);

        return to_route('settings.professionals.index');
    }

    public function edit(Professional $professional, TenantContext $tenant, ProfessionalOperationalStatusResolver $operationalStatusResolver): Response
    {
        $this->authorize('view', $professional);

        $professional->load('user:id,name');

        return Inertia::render('settings/professionals/Edit', [
            'professional' => [
                'id' => $professional->id,
                'name' => $professional->name,
                'social_name' => $professional->social_name,
                'display_name' => $professional->display_name,
                'email' => $professional->email,
                'phone' => $professional->phone,
                'document' => self::maskDocument($professional->document),
                'birth_date' => $professional->birth_date?->format('Y-m-d'),
                'bio' => $professional->bio,
                'photo_url' => $professional->photo_path ? Storage::disk('public')->url($professional->photo_path) : null,
                'status' => $professional->status->value,
                'linked_user' => $professional->user ? ['id' => $professional->user->id, 'name' => $professional->user->name] : null,
            ],
            'eligibleUsers' => $this->eligibleUsers($tenant),
            'operationalSummary' => $this->operationalSummary($professional, $operationalStatusResolver),
        ]);
    }

    /** @return array<string, mixed> */
    private function operationalSummary(Professional $professional, ProfessionalOperationalStatusResolver $operationalStatusResolver): array
    {
        $status = $operationalStatusResolver->resolve($professional);

        $primaryUnit = $professional->primaryUnitLink()->with('unit:id,name')->first();
        $primarySpecialty = $professional->primarySpecialtyLink()->with('specialty:id,name')->first();
        $primaryRegistration = $professional->primaryRegistration;
        $nextTimeBlock = $professional->timeBlocks()
            ->where('status', RecordStatus::Active)
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        return array_merge($status->toArray(), [
            'primary_unit' => $primaryUnit?->unit ? ['id' => $primaryUnit->unit->id, 'name' => $primaryUnit->unit->name] : null,
            'active_units_count' => $professional->unitLinks()->where('status', RecordStatus::Active)->count(),
            'primary_specialty' => $primarySpecialty?->specialty ? ['id' => $primarySpecialty->specialty->id, 'name' => $primarySpecialty->specialty->name] : null,
            'specialties_count' => $professional->specialtyLinks()->count(),
            'primary_registration' => $primaryRegistration ? [
                'council' => $primaryRegistration->council,
                'masked_number' => $primaryRegistration->maskedRegistrationNumber(),
                'validity_status' => $primaryRegistration->validityStatus()->value,
            ] : null,
            'active_services_count' => $professional->serviceLinks()->where('status', RecordStatus::Active)->count(),
            'has_working_hours' => $professional->workingHours()->where('professional_working_hours.status', RecordStatus::Active)->exists(),
            'next_time_block' => $nextTimeBlock ? [
                'type' => $nextTimeBlock->type->value,
                'starts_at' => $nextTimeBlock->starts_at->toIso8601String(),
            ] : null,
        ]);
    }

    public function update(UpdateProfessionalRequest $request, Professional $professional, UpdateProfessionalAction $action): RedirectResponse
    {
        $action->handle($professional, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional alterado com sucesso.']);

        return to_route('settings.professionals.index');
    }

    public function activate(Professional $professional, ActivateProfessionalAction $action): RedirectResponse
    {
        $this->authorize('activate', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional ativado com sucesso.']);

        return back();
    }

    public function deactivate(Professional $professional, DeactivateProfessionalAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional inativado com sucesso.']);

        return back();
    }

    public function linkUser(LinkProfessionalUserRequest $request, Professional $professional, LinkProfessionalUserAction $action): RedirectResponse
    {
        $action->handle($professional, (int) $request->validated('user_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário vinculado ao profissional com sucesso.']);

        return back();
    }

    public function unlinkUser(Professional $professional, UnlinkProfessionalUserAction $action): RedirectResponse
    {
        $this->authorize('linkUser', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo com o usuário removido com sucesso.']);

        return back();
    }

    public function updatePhoto(UpdateProfessionalPhotoRequest $request, Professional $professional, UpdateProfessionalPhotoAction $action): RedirectResponse
    {
        $action->handle($professional, $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto atualizada com sucesso.']);

        return back();
    }

    public function destroyPhoto(Professional $professional, DestroyProfessionalPhotoAction $action): RedirectResponse
    {
        $this->authorize('update', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto removida com sucesso.']);

        return back();
    }

    public function destroy(Professional $professional, DeleteProfessionalAction $action): RedirectResponse
    {
        $this->authorize('delete', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional excluído com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(string $professional, TenantContext $tenant, RestoreProfessionalAction $action): RedirectResponse
    {
        $entity = Professional::withTrashed()->findOrFail($professional);

        if (! $tenant->organization() || $entity->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $entity);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional restaurado com sucesso.']);

        return back();
    }

    /**
     * Página "Especialidades e registros" do perfil operacional do
     * profissional — especialidade e registro em conselho são conceitos
     * diferentes (ver App\Models\ProfessionalSpecialty/ProfessionalRegistration),
     * agrupados na mesma seção de navegação por estarem relacionados.
     */
    public function specialties(Professional $professional): Response
    {
        $this->authorize('view', $professional);

        $professional->load(['specialtyLinks' => fn ($query) => $query->withTrashed()->with('specialty:id,name,status')]);
        $professional->load(['registrations' => fn ($query) => $query->withTrashed()]);

        $canViewSensitive = request()->user()?->can('viewSensitive', new ProfessionalRegistration(['organization_id' => $professional->organization_id])) === true;

        $linkedSpecialtyIds = $professional->specialtyLinks
            ->reject(fn (ProfessionalSpecialty $link) => $link->trashed())
            ->pluck('specialty_id');

        $eligibleSpecialties = $professional->organization
            ->specialties()
            ->where('status', 'active')
            ->whereNotIn('id', $linkedSpecialtyIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($specialty) => ['id' => $specialty->id, 'name' => $specialty->name]);

        return Inertia::render('settings/professionals/Specialties', [
            'professional' => $this->professionalSummary($professional),
            'specialtyLinks' => $professional->specialtyLinks->map(fn (ProfessionalSpecialty $link) => [
                'id' => $link->id,
                'specialty' => ['id' => $link->specialty->id, 'name' => $link->specialty->name],
                'is_primary' => $link->is_primary,
                'status' => $link->status->value,
                'deleted_at' => $link->deleted_at,
            ]),
            'eligibleSpecialties' => $eligibleSpecialties,
            'registrations' => $professional->registrations->map(fn (ProfessionalRegistration $registration) => [
                'id' => $registration->id,
                'council' => $registration->council,
                'registration_type' => $registration->registration_type,
                'masked_registration_number' => $registration->maskedRegistrationNumber(),
                'state' => $registration->state?->value,
                'issued_at' => $registration->issued_at?->format('Y-m-d'),
                'expires_at' => $registration->expires_at?->format('Y-m-d'),
                'validity_status' => $registration->validityStatus()->value,
                'is_primary' => $registration->is_primary,
                'status' => $registration->status->value,
                'deleted_at' => $registration->deleted_at,
            ]),
            'canViewSensitiveRegistrations' => $canViewSensitive,
        ]);
    }

    public function units(Professional $professional): Response
    {
        $this->authorize('view', $professional);

        $professional->load(['unitLinks' => fn ($query) => $query->withTrashed()->with('unit:id,name,code,status')]);

        $linkedUnitIds = $professional->unitLinks
            ->reject(fn (ProfessionalUnit $link) => $link->trashed())
            ->pluck('unit_id');

        $eligibleUnits = $professional->organization
            ->units()
            ->where('status', 'active')
            ->whereNotIn('id', $linkedUnitIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($unit) => ['id' => $unit->id, 'name' => $unit->name]);

        return Inertia::render('settings/professionals/Units', [
            'professional' => $this->professionalSummary($professional),
            'unitLinks' => $professional->unitLinks->map(fn (ProfessionalUnit $link) => [
                'id' => $link->id,
                'unit' => ['id' => $link->unit->id, 'name' => $link->unit->name],
                'is_primary' => $link->is_primary,
                'status' => $link->status->value,
                'starts_on' => $link->starts_on?->format('Y-m-d'),
                'ends_on' => $link->ends_on?->format('Y-m-d'),
                'vigency_status' => $link->vigencyStatus()->value,
                'deleted_at' => $link->deleted_at,
            ]),
            'eligibleUnits' => $eligibleUnits,
        ]);
    }

    public function services(Professional $professional): Response
    {
        $this->authorize('view', $professional);

        $professional->load(['serviceLinks' => fn ($query) => $query->withTrashed()->with(['service.organization:id', 'unitLinks.unit:id,name'])]);
        $professional->serviceLinks->each(fn (ProfessionalService $link) => $link->setRelation('professional', $professional));

        $linkedServiceIds = $professional->serviceLinks
            ->reject(fn (ProfessionalService $link) => $link->trashed())
            ->pluck('service_id');

        $eligibleServices = $professional->organization
            ->services()
            ->where('status', 'active')
            ->whereNotIn('id', $linkedServiceIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($service) => ['id' => $service->id, 'name' => $service->name]);

        $professionalUnits = $professional->unitLinks()
            ->where('status', 'active')
            ->with('unit:id,name')
            ->get()
            ->map(fn (ProfessionalUnit $link) => ['id' => $link->unit->id, 'name' => $link->unit->name]);

        return Inertia::render('settings/professionals/Services', [
            'professional' => $this->professionalSummary($professional),
            'serviceLinks' => $professional->serviceLinks->map(fn (ProfessionalService $link) => [
                'id' => $link->id,
                'service' => ['id' => $link->service->id, 'name' => $link->service->name],
                'status' => $link->status->value,
                'unit_scope' => $link->unit_scope->value,
                'selected_unit_ids' => $link->unitLinks->pluck('unit_id'),
                'compatible_units' => $link->trashed() ? [] : $link->compatibleUnitIds()->values(),
                'duration_minutes' => ['default' => $link->service->default_duration_minutes, 'custom' => $link->custom_duration_minutes, 'effective' => $link->effectiveDurationMinutes(), 'is_inherited' => $link->isDurationInherited()],
                'price_cents' => ['default' => $link->service->default_price_cents, 'custom' => $link->custom_price_cents, 'effective' => $link->effectivePriceCents(), 'is_inherited' => $link->isPriceInherited()],
                'buffer_before_minutes' => ['default' => $link->service->buffer_before_minutes, 'custom' => $link->custom_buffer_before_minutes, 'effective' => $link->effectiveBufferBeforeMinutes(), 'is_inherited' => $link->isBufferBeforeInherited()],
                'buffer_after_minutes' => ['default' => $link->service->buffer_after_minutes, 'custom' => $link->custom_buffer_after_minutes, 'effective' => $link->effectiveBufferAfterMinutes(), 'is_inherited' => $link->isBufferAfterInherited()],
                'deleted_at' => $link->deleted_at,
            ]),
            'eligibleServices' => $eligibleServices,
            'professionalUnits' => $professionalUnits,
        ]);
    }

    /**
     * Página "Jornada e disponibilidade" — jornada sempre agrupada por
     * vínculo profissional-unidade (App\Models\ProfessionalUnit), que já
     * carrega a unidade, o fuso e a vigência do vínculo em si.
     */
    public function availability(Professional $professional): Response
    {
        $this->authorize('view', $professional);

        $professional->load(['unitLinks' => fn ($query) => $query
            ->with(['unit:id,name,timezone,status', 'unit.openingHours', 'workingHours' => fn ($workingHoursQuery) => $workingHoursQuery->withTrashed()->orderBy('weekday')->orderBy('starts_at')]),
        ]);

        return Inertia::render('settings/professionals/Availability', [
            'professional' => $this->professionalSummary($professional),
            'units' => $professional->unitLinks
                ->map(fn (ProfessionalUnit $link) => $this->availabilityUnitSummary($professional, $link))
                ->values()
                ->all(),
        ]);
    }

    /** @return array{professional_unit_id: string, unit: array{id: string, name: string, timezone: string}, unit_link_status: string, opening_hours: array<int, array{day_of_week: int, opens_at: string, closes_at: string}>, can_manage: bool, working_hours: array<int, array{id: string, weekday: int, starts_at: string, ends_at: string, effective_from: ?string, effective_until: ?string, status: string, vigency_status: string, is_within_opening_hours: bool, deleted_at: mixed}>} */
    private function availabilityUnitSummary(Professional $professional, ProfessionalUnit $link): array
    {
        return [
            'professional_unit_id' => $link->id,
            'unit' => ['id' => $link->unit->id, 'name' => $link->unit->name, 'timezone' => $link->unit->timezone],
            'unit_link_status' => $link->status->value,
            'opening_hours' => $link->unit->openingHours
                ->map(fn ($openingHour) => [
                    'day_of_week' => (int) $openingHour->day_of_week,
                    'opens_at' => substr((string) $openingHour->opens_at, 0, 5),
                    'closes_at' => substr((string) $openingHour->closes_at, 0, 5),
                ])
                ->values()
                ->all(),
            'can_manage' => request()->user()?->can('manageAvailability', [$professional, $link->unit]) === true,
            'working_hours' => $link->workingHours
                ->map(fn (ProfessionalWorkingHour $workingHour) => [
                    'id' => $workingHour->id,
                    'weekday' => $workingHour->weekday->value,
                    'starts_at' => substr($workingHour->starts_at, 0, 5),
                    'ends_at' => substr($workingHour->ends_at, 0, 5),
                    'effective_from' => $workingHour->effective_from?->format('Y-m-d'),
                    'effective_until' => $workingHour->effective_until?->format('Y-m-d'),
                    'status' => $workingHour->status->value,
                    'vigency_status' => $workingHour->vigencyStatus()->value,
                    'is_within_opening_hours' => $workingHour->trashed()
                        ? true
                        : WorkingHourOpeningHoursGuard::isWithinOpeningHours($link->unit, $workingHour->weekday, $workingHour->starts_at, $workingHour->ends_at),
                    'deleted_at' => $workingHour->deleted_at,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Página "Ausências e bloqueios" — observações internas só são
     * incluídas para quem poderia gerenciar aquele bloqueio específico
     * (mesma checagem usada para autorizar a edição).
     */
    public function timeBlocks(Professional $professional): Response
    {
        $this->authorize('view', $professional);

        $professional->load(['timeBlocks' => fn ($query) => $query->withTrashed()->with('unit:id,name')->orderByDesc('starts_at')]);

        $eligibleUnits = $professional->unitLinks()
            ->with('unit:id,name')
            ->get()
            ->map(fn (ProfessionalUnit $link) => ['id' => $link->unit->id, 'name' => $link->unit->name])
            ->unique('id')
            ->values()
            ->all();

        return Inertia::render('settings/professionals/TimeBlocks', [
            'professional' => $this->professionalSummary($professional),
            'timeBlocks' => $professional->timeBlocks
                ->map(fn (ProfessionalTimeBlock $timeBlock) => $this->timeBlockSummary($professional, $timeBlock))
                ->values()
                ->all(),
            'eligibleUnits' => $eligibleUnits,
        ]);
    }

    /** @return array{id: string, type: string, scope: string, unit: ?array{id: string, name: string}, timezone: string, starts_at: string, ends_at: string, is_all_day: bool, reason: ?string, internal_notes: ?string, status: string, temporal_status: string, can_manage: bool, deleted_at: mixed} */
    private function timeBlockSummary(Professional $professional, ProfessionalTimeBlock $timeBlock): array
    {
        $canManage = request()->user()?->can('manageTimeBlocks', [$professional, $timeBlock->unit]) === true;

        return [
            'id' => $timeBlock->id,
            'type' => $timeBlock->type->value,
            'scope' => $timeBlock->scope->value,
            'unit' => $timeBlock->unit ? ['id' => $timeBlock->unit->id, 'name' => $timeBlock->unit->name] : null,
            'timezone' => $timeBlock->unit->timezone ?? $professional->referenceTimezone(),
            'starts_at' => $timeBlock->starts_at->toIso8601String(),
            'ends_at' => $timeBlock->ends_at->toIso8601String(),
            'is_all_day' => $timeBlock->is_all_day,
            'reason' => $timeBlock->reason,
            'internal_notes' => $canManage ? $timeBlock->internal_notes : null,
            'status' => $timeBlock->status->value,
            'temporal_status' => $timeBlock->temporalStatus()->value,
            'can_manage' => $canManage,
            'deleted_at' => $timeBlock->deleted_at,
        ];
    }

    /** @return array{id: string, display_name: string, status: string} */
    private function professionalSummary(Professional $professional): array
    {
        return [
            'id' => $professional->id,
            'display_name' => $professional->display_name,
            'status' => $professional->status->value,
        ];
    }

    /** @return array<int, array{id: int, name: string, email: string}> */
    private function eligibleUsers(TenantContext $tenant): array
    {
        $organization = $tenant->organization();

        $alreadyLinkedUserIds = Professional::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active)
            ->whereNotIn('user_id', $alreadyLinkedUserIds)
            ->with('user:id,name,email')
            ->get()
            ->map(fn (OrganizationMembership $membership) => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: string, name: string}> */
    private function activeUnitOptions(TenantContext $tenant): array
    {
        return $tenant->organization()
            ->units()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($unit) => ['id' => $unit->id, 'name' => $unit->name])
            ->all();
    }

    /** @return array<int, array{id: string, name: string}> */
    private function activeSpecialtyOptions(TenantContext $tenant): array
    {
        return $tenant->organization()
            ->specialties()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($specialty) => ['id' => $specialty->id, 'name' => $specialty->name])
            ->all();
    }

    private static function maskDocument(?string $document): ?string
    {
        if ($document === null || $document === '') {
            return null;
        }

        try {
            return Document::fromType(LegalEntityType::Individual, $document)->masked();
        } catch (InvalidArgumentException) {
            return '****';
        }
    }

    private static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) <= 4) {
            return str_repeat('•', strlen($digits));
        }

        return str_repeat('•', strlen($digits) - 4).substr($digits, -4);
    }
}
