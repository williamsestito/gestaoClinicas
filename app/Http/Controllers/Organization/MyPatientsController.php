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
        )->through(fn ($patient) => [
            'id' => $patient->id,
            'name' => $patient->name,
            'preferred_name' => $patient->preferred_name,
            'document' => $patient->document ? Document::fromCpf($patient->document)->masked() : null,
            'birth_date' => $patient->birth_date->toDateString(),
            'phone' => $patient->phone,
            'status' => $patient->status->value,
            'deleted_at' => $patient->deleted_at,
        ]);

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
