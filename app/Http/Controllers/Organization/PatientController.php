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
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Patient;
use App\Models\SessionPackage;
use App\Models\User;
use App\Queries\PatientDuplicateQuery;
use App\Queries\PatientListQuery;
use App\Support\Documents\BrazilianState;
use App\Support\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $professionalId = $request->string('professional_id')->value() ?: null;

        $patients = $query->forOrganization(
            $tenant->organization(),
            $request->string('search')->value() ?: null,
            $status !== '' ? RecordStatus::from($status) : null,
            primaryProfessionalId: $professionalId,
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
            'professionals' => $tenant->organization()->professionals()
                ->where('status', RecordStatus::Active)
                ->orderBy('display_name')
                ->get(['id', 'display_name']),
            'filters' => $request->only(['search', 'status', 'professional_id']),
        ]);
    }

    /**
     * Aceita nome/telefone/e-mail/documento como query string opcional
     * (nunca validados aqui, só pré-preenchimento de UX) — usado pelo link
     * "Cadastrar novo paciente" a partir de um pré-agendamento sem paciente
     * casado (ver PatientSearchSelect.vue), reaproveitando os dados já
     * digitados pelo lead em vez de a recepção/profissional ter que
     * redigitar tudo em uma aba separada.
     */
    public function create(Request $request, TenantContext $tenant): Response
    {
        $this->authorize('create', [Patient::class, $tenant->organization()]);

        $prefill = array_filter([
            'name' => $request->string('name')->value() ?: null,
            'phone' => $request->string('phone')->value() ?: null,
            'email' => $request->string('email')->value() ?: null,
            'document' => $request->string('document')->value() ?: null,
        ]);

        return Inertia::render('settings/patients/Create', [
            'states' => BrazilianState::codes(),
            'prefill' => $prefill !== [] ? $prefill : null,
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

        $patient->loadMissing(['address', 'responsibles', 'emergencyContacts', 'portalLink', 'sessionPackages' => fn ($query) => $query->with('service:id,name')->latest()]);

        return Inertia::render('settings/patients/Edit', [
            'states' => BrazilianState::codes(),
            'services' => $patient->organization->services()->where('status', RecordStatus::Active)->orderBy('name')->get(['id', 'name']),
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
            'sessionPackages' => $patient->sessionPackages->map(fn (SessionPackage $package) => [
                'id' => $package->id,
                'service_name' => $package->service?->name,
                'total_sessions' => $package->total_sessions,
                'remaining_sessions' => $package->remainingSessions(),
                'expires_at' => $package->expires_at?->toDateString(),
                'status' => $package->status->value,
                'is_expired' => $package->isExpired(),
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
        $organization = $tenant->organization();

        $this->authorize('viewAny', [Patient::class, $organization]);

        $query = trim((string) $request->string('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['patients' => []]);
        }

        $digits = Document::onlyDigits($query);

        $patientsQuery = $organization->patients()
            ->where(function ($inner) use ($query, $digits) {
                $inner->where('name', 'ilike', "%{$query}%");

                if ($digits !== '') {
                    $inner->orWhere('document', 'like', "%{$digits}%");
                }
            });

        // `viewAny()` também aceita o autoatendimento do profissional
        // (só `patients.view-own`) — sem acesso amplo, a busca nunca
        // extrapola os pacientes do próprio profissional (mesma definição
        // de "paciente próprio" de PatientPolicy::hasOwnAccess()), nunca
        // qualquer paciente da organização.
        if (! Gate::allows('viewAnyBroad', [Patient::class, $organization])) {
            /** @var User $user */
            $user = $request->user();

            $professional = $user->professionals()
                ->where('organization_id', $organization->id)
                ->where('status', RecordStatus::Active)
                ->first();

            $patientsQuery->where('primary_professional_id', $professional?->id);
        }

        $patients = $patientsQuery
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

    /**
     * Endpoint JSON do modal de detalhes do paciente ("Meus pacientes"/
     * admin) — histórico de agendamentos agrupado por profissional (quando
     * mais de um já atendeu) e pré-agendamentos ainda pendentes. Nunca
     * inclui conteúdo de prontuário clínico — isso continua atrás de
     * App\Policies\MedicalRecordPolicy::viewPatientHistory(), na tela
     * dedicada.
     *
     * Dois níveis de acesso (ver App\Policies\PatientPolicy):
     * `view()` completo mostra tudo; `viewSummary()` (quem só tem um
     * pré-agendamento pendente com o próprio profissional, nunca atendeu de
     * verdade) mostra só o essencial — nome/telefone/status e só os
     * próprios pré-agendamentos, nunca os de outro profissional com este
     * mesmo paciente.
     */
    public function summary(Request $request, Patient $patient): JsonResponse
    {
        $fullAccess = Gate::allows('view', $patient);

        if (! $fullAccess && ! Gate::allows('viewSummary', $patient)) {
            abort(403);
        }

        $pendingRequestsQuery = $patient->appointmentRequests()->whereNull('appointment_id');

        if (! $fullAccess) {
            /** @var User $user */
            $user = $request->user();
            $ownProfessionalId = $user->professionals()
                ->where('organization_id', $patient->organization_id)
                ->value('id');

            $pendingRequestsQuery->where('professional_id', $ownProfessionalId);
        }

        $appointmentsByProfessional = $this->groupAppointmentsByProfessional($patient);

        $pendingRequests = $pendingRequestsQuery
            ->with('professional:id,display_name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AppointmentRequest $appointmentRequest) => [
                'id' => $appointmentRequest->id,
                'professional_name' => $appointmentRequest->professional?->display_name,
                'status_label' => $appointmentRequest->status->label(),
                'created_at' => $appointmentRequest->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'full_access' => $fullAccess,
            'can_view_medical_record' => Gate::allows('viewPatientHistory', $patient),
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'preferred_name' => $patient->preferred_name,
                'phone' => $patient->phone,
                'email' => $fullAccess ? $patient->email : null,
                'birth_date' => $fullAccess ? $patient->birth_date->toDateString() : null,
                'document' => $fullAccess && $patient->document ? Document::fromCpf($patient->document)->masked() : null,
                'status' => $patient->status->value,
            ],
            'appointments_by_professional' => $appointmentsByProfessional,
            'pending_requests' => $pendingRequests,
        ]);
    }

    /**
     * @return array<int, array{professional_name: string, appointments: array<int, array{id: string, starts_at: string, status_label: string, service_name: string|null}>}>
     */
    private function groupAppointmentsByProfessional(Patient $patient): array
    {
        return $patient->appointments()
            ->with(['professional:id,display_name', 'service:id,name'])
            ->orderByDesc('starts_at')
            ->get()
            ->groupBy('professional_id')
            ->values()
            ->map(function ($appointments) {
                $first = $appointments->first();

                return [
                    'professional_name' => (string) $first->professional->display_name,
                    'appointments' => $appointments->map(fn (Appointment $appointment) => [
                        'id' => $appointment->id,
                        'starts_at' => $appointment->starts_at->toIso8601String(),
                        'status_label' => $appointment->status->label(),
                        'service_name' => $appointment->service?->name,
                    ])->values()->all(),
                ];
            })
            ->all();
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
