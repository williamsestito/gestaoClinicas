<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Professional;
use App\Models\User;
use App\Queries\PatientListQuery;
use App\Support\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ponto de entrada de "Meus pacientes" — nunca aceita um `professional_id`
 * do frontend: o profissional é sempre resolvido a partir do usuário
 * autenticado e da organização ativa, mesmo padrão de
 * App\Http\Controllers\Organization\MyScheduleController. Lista somente
 * pacientes com `primary_professional_id` apontando para esse profissional
 * — nunca a base completa de pacientes da clínica (isso exige
 * `patients.view`/`patients.manage`, ver PatientPolicy::viewAny()).
 */
class MyPatientsController extends Controller
{
    public function index(Request $request, TenantContext $tenant, PatientListQuery $query): Response
    {
        $organization = $tenant->organization();
        $professional = $organization ? $this->resolveOwnProfessional($organization->id) : null;

        if ($organization === null || $professional === null) {
            return Inertia::render('settings/my-patients/Index', ['patients' => null, 'filters' => []]);
        }

        $status = $request->string('status')->value();

        $patients = $query->forOrganization(
            $organization,
            $request->string('search')->value() ?: null,
            $status !== '' ? RecordStatus::from($status) : null,
            primaryProfessionalId: $professional->id,
        )->through(function ($patient) use ($professional) {
            // Mesma definição de acesso completo de
            // PatientPolicy::hasOwnAccess() — quem só tem um
            // pré-agendamento pendente com este profissional (nunca foi
            // atendido de verdade, nunca é o principal) vê o paciente na
            // lista, mas sem os botões de ação: abrir o cadastro/prontuário
            // agora resultaria em 403.
            $isPrimary = $patient->primary_professional_id === $professional->id;
            $hasAppointment = $patient->appointments()->where('professional_id', $professional->id)->exists();

            return [
                'id' => $patient->id,
                'name' => $patient->name,
                'preferred_name' => $patient->preferred_name,
                'document' => $patient->document ? Document::fromCpf($patient->document)->masked() : null,
                'birth_date' => $patient->birth_date->toDateString(),
                'phone' => $patient->phone,
                'status' => $patient->status->value,
                'deleted_at' => $patient->deleted_at,
                'full_access' => $isPrimary || $hasAppointment,
                'relationship_label' => match (true) {
                    $isPrimary => 'Paciente principal',
                    $hasAppointment => 'Já atendido',
                    default => 'Pré-agendamento pendente',
                },
            ];
        });

        return Inertia::render('settings/my-patients/Index', [
            'patients' => $patients,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    private function resolveOwnProfessional(string $organizationId): ?Professional
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->professionals()
            ->where('organization_id', $organizationId)
            ->where('status', RecordStatus::Active)
            ->first();
    }
}
