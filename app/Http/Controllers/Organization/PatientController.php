<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivatePatientAction;
use App\Actions\Organization\CreatePatientAction;
use App\Actions\Organization\DeactivatePatientAction;
use App\Actions\Organization\DeletePatientAction;
use App\Actions\Organization\DestroyPatientPhotoAction;
use App\Actions\Organization\RestorePatientAction;
use App\Actions\Organization\UpdatePatientAction;
use App\Actions\Organization\UpdatePatientPhotoAction;
use App\Data\Organization\AddressData;
use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreatePatientRequest;
use App\Http\Requests\Organization\UpdatePatientPhotoRequest;
use App\Http\Requests\Organization\UpdatePatientRequest;
use App\Models\Patient;
use App\Queries\PatientDuplicateQuery;
use App\Queries\PatientListQuery;
use App\Support\Documents\BrazilianState;
use App\Support\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    public function index(Request $request, TenantContext $tenant, PatientListQuery $query): Response
    {
        $this->authorize('viewAny', [Patient::class, $tenant->organization()]);

        $status = $request->string('status')->value();

        $patients = $query->forOrganization(
            $tenant->organization(),
            $request->string('search')->value() ?: null,
            $status !== '' ? RecordStatus::from($status) : null,
        )->through(fn (Patient $patient) => [
            'id' => $patient->id,
            'name' => $patient->name,
            'preferred_name' => $patient->preferred_name,
            'document' => $patient->document ? Document::fromCpf($patient->document)->masked() : null,
            'birth_date' => $patient->birth_date->toDateString(),
            'phone' => $patient->phone,
            'status' => $patient->status->value,
            'deleted_at' => $patient->deleted_at,
        ]);

        return Inertia::render('settings/patients/Index', [
            'patients' => $patients,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [Patient::class, $tenant->organization()]);

        return Inertia::render('settings/patients/Create', [
            'states' => BrazilianState::codes(),
        ]);
    }

    public function store(CreatePatientRequest $request, CreatePatientAction $action, TenantContext $tenant): RedirectResponse
    {
        $validated = $request->validated();

        $action->handle(
            $tenant->organization(),
            collect($validated)->except(['address', 'emergency_contacts', 'responsibles'])->all(),
            $this->resolveAddressData($validated['address'] ?? null),
            $validated['emergency_contacts'] ?? [],
            $validated['responsibles'] ?? [],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente cadastrado com sucesso.']);

        return to_route('settings.patients.index');
    }

    public function edit(Patient $patient): Response
    {
        $this->authorize('view', $patient);

        $patient->loadMissing(['address', 'responsibles', 'emergencyContacts', 'portalLink']);

        return Inertia::render('settings/patients/Edit', [
            'states' => BrazilianState::codes(),
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'preferred_name' => $patient->preferred_name,
                'document' => $patient->document,
                'birth_date' => $patient->birth_date->toDateString(),
                'phone' => $patient->phone,
                'whatsapp' => $patient->whatsapp,
                'email' => $patient->email,
                'origin' => $patient->origin,
                'preferred_unit_id' => $patient->preferred_unit_id,
                'primary_professional_id' => $patient->primary_professional_id,
                'status' => $patient->status->value,
                'photo_url' => $patient->photo_path ? route('settings.patients.photo.show', $patient) : null,
                'is_minor' => $patient->isMinor(),
                'has_portal_account' => $patient->portalLink !== null,
            ],
            'address' => $patient->address ? [
                'postal_code' => $patient->address->postal_code,
                'street' => $patient->address->street,
                'number' => $patient->address->number,
                'complement' => $patient->address->complement,
                'neighborhood' => $patient->address->neighborhood,
                'city' => $patient->address->city,
                'state' => $patient->address->state,
            ] : null,
            'responsibles' => $patient->responsibles->map(fn ($responsible) => [
                'id' => $responsible->id,
                'name' => $responsible->name,
                'phone' => $responsible->phone,
                'relationship' => $responsible->relationship,
                'is_legal_guardian' => $responsible->is_legal_guardian,
                'is_financial_responsible' => $responsible->is_financial_responsible,
                'is_authorized_representative' => $responsible->is_authorized_representative,
            ]),
            'emergencyContacts' => $patient->emergencyContacts->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'relationship' => $contact->relationship,
                'phone_primary' => $contact->phone_primary,
                'phone_secondary' => $contact->phone_secondary,
            ]),
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient, UpdatePatientAction $action): RedirectResponse
    {
        $validated = $request->validated();

        $action->handle(
            $patient,
            collect($validated)->except(['address'])->all(),
            $this->resolveAddressData($validated['address'] ?? null),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente alterado com sucesso.']);

        return to_route('settings.patients.index');
    }

    public function activate(Patient $patient, ActivatePatientAction $action): RedirectResponse
    {
        $this->authorize('activate', $patient);

        $action->handle($patient);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente ativado com sucesso.']);

        return back();
    }

    public function deactivate(Patient $patient, DeactivatePatientAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $patient);

        $action->handle($patient);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente inativado com sucesso.']);

        return back();
    }

    public function destroy(Patient $patient, DeletePatientAction $action): RedirectResponse
    {
        $this->authorize('delete', $patient);

        $action->handle($patient);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente excluído com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(string $patient, TenantContext $tenant, RestorePatientAction $action): RedirectResponse
    {
        $entity = Patient::withTrashed()->findOrFail($patient);

        if (! $tenant->organization() || $entity->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $entity);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente restaurado com sucesso.']);

        return back();
    }

    public function updatePhoto(UpdatePatientPhotoRequest $request, Patient $patient, UpdatePatientPhotoAction $action): RedirectResponse
    {
        $action->handle($patient, $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto atualizada com sucesso.']);

        return back();
    }

    public function destroyPhoto(Patient $patient, DestroyPatientPhotoAction $action): RedirectResponse
    {
        $this->authorize('update', $patient);

        $action->handle($patient);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto removida com sucesso.']);

        return back();
    }

    /**
     * Endpoint JSON de busca por nome/documento — usado pela seleção de
     * paciente na criação de agendamento (Etapa 3.1). Diferente de
     * duplicates(), que exige nome+nascimento combinados para achar
     * duplicidade; aqui basta um texto parcial.
     */
    public function search(Request $request, TenantContext $tenant): JsonResponse
    {
        $this->authorize('viewAny', [Patient::class, $tenant->organization()]);

        $query = trim((string) $request->string('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['patients' => []]);
        }

        $digits = Document::onlyDigits($query);

        $patients = $tenant->organization()->patients()
            ->where(function ($inner) use ($query, $digits) {
                $inner->where('name', 'ilike', "%{$query}%");

                if ($digits !== '') {
                    $inner->orWhere('document', 'like', "%{$digits}%");
                }
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'preferred_name', 'birth_date'])
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'name' => $patient->preferred_name ?: $patient->name,
                'birth_date' => $patient->birth_date->toDateString(),
            ]);

        return response()->json(['patients' => $patients]);
    }

    /**
     * Serve a foto do paciente do disco privado `local`, atrás da mesma
     * Policy de visualização do paciente — nunca um link público direto
     * (diferente da foto do profissional, propositalmente pública).
     */
    public function showPhoto(Patient $patient): StreamedResponse
    {
        $this->authorize('view', $patient);

        if (! $patient->photo_path || ! Storage::disk('local')->exists($patient->photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($patient->photo_path);
    }

    /**
     * Endpoint JSON (não uma página Inertia) — consultado pelo formulário de
     * criação/edição, com debounce, antes do submit. Só avisa, nunca bloqueia
     * (ver App\Queries\PatientDuplicateQuery).
     */
    public function duplicates(Request $request, TenantContext $tenant, PatientDuplicateQuery $query): JsonResponse
    {
        $this->authorize('create', [Patient::class, $tenant->organization()]);

        $matches = $query->search(
            $tenant->organization(),
            $request->string('document')->value() ?: null,
            $request->string('phone')->value() ?: null,
            $request->string('email')->value() ?: null,
            $request->string('name')->value() ?: null,
            $request->string('birth_date')->value() ?: null,
        )->map(fn (Patient $patient) => [
            'id' => $patient->id,
            'name' => $patient->name,
            'document' => $patient->document ? Document::fromCpf($patient->document)->masked() : null,
            'phone' => $patient->phone,
            'birth_date' => $patient->birth_date->toDateString(),
        ])->values();

        return response()->json(['matches' => $matches]);
    }

    /** @param array<string, mixed>|null $address */
    private function resolveAddressData(?array $address): ?AddressData
    {
        if ($address === null || array_filter($address) === []) {
            return null;
        }

        return AddressData::fromArray(array_merge([
            'postal_code' => '',
            'complement' => null,
        ], $address));
    }
}
