<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Pacientes são escopados por organização, não por unidade (ver
 * docs/modules/patients.md). Diferente de ProfessionalPolicy::viewAny(),
 * aqui a visualização exige explicitamente `PatientsView`/`PatientsManage`
 * (ou o acesso próprio do profissional vinculado) — dado pessoal sensível
 * não é visível a qualquer membro ativo só por estar na organização.
 */
class PatientPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($organization === null) {
            return $user->is_platform_admin;
        }

        return $this->viewAnyBroad($user, $organization)
            || $this->hasOwnViewAnyAccess($user, $organization->id);
    }

    /**
     * Acesso amplo (sem escopo a um profissional específico) — usado
     * também por `PatientController::search()` para decidir se o resultado
     * da busca deve ser restrito aos pacientes do próprio profissional
     * (ver `hasOwnViewAnyAccess()` abaixo), quando `viewAny()` só passou
     * pelo caminho de autoatendimento.
     */
    public function viewAnyBroad(User $user, Organization $organization): bool
    {
        return $this->hasBroadAccess($user, $organization->id)
            || $this->permissionChecker->can($user, PermissionKey::PatientsView, $organization->id);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id)
            || $this->permissionChecker->can($user, PermissionKey::PatientsView, $patient->organization_id)
            || $this->hasOwnAccess($user, $patient);
    }

    /**
     * Acesso resumido — usado pelo modal de detalhes ("Meus pacientes"/
     * admin): quem já tem `view()` completo passa direto; quem não, ainda
     * assim vê o essencial (nome/telefone/status/profissional solicitado)
     * se tiver ao menos um pré-agendamento pendente com o profissional
     * vinculado ao seu usuário — nunca libera endereço, responsáveis ou
     * prontuário, só a existência da solicitação.
     */
    public function viewSummary(User $user, Patient $patient): bool
    {
        if ($this->view($user, $patient)) {
            return true;
        }

        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        if (! $this->hasActiveMembership($user, $patient->organization_id)) {
            return false;
        }

        if (! $this->permissionChecker->can($user, PermissionKey::PatientsViewOwn, $patient->organization_id)) {
            return false;
        }

        $professional = Professional::query()
            ->where('organization_id', $patient->organization_id)
            ->where('user_id', $user->id)
            ->first();

        if ($professional === null) {
            return false;
        }

        return $patient->appointmentRequests()->where('professional_id', $professional->id)->exists();
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasBroadAccess($user, $organization->id);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function activate(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function deactivate(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function restore(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function manageResponsibles(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    public function manageEmergencyContacts(User $user, Patient $patient): bool
    {
        return $this->hasBroadAccess($user, $patient->organization_id);
    }

    private function hasBroadAccess(User $user, string $organizationId): bool
    {
        return $this->hasActiveMembership($user, $organizationId, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::PatientsManage, $organizationId);
    }

    /**
     * Autoatendimento do profissional: só leitura, nunca gestão. Libera
     * quando o profissional vinculado ao usuário é o `primary_professional_id`
     * do paciente **ou** já tem ao menos um `Appointment` de verdade com
     * ele — achado em uso real: vários profissionais podem atender o mesmo
     * paciente ao longo do tempo, não só o "principal", e ficavam sem
     * conseguir ver o cadastro de alguém que realmente atenderam. Nunca
     * libera só por um pré-agendamento pendente (`AppointmentRequest`) —
     * isso é responsabilidade de "Meus pacientes" mostrar de forma
     * resumida, não deste método.
     */
    private function hasOwnAccess(User $user, Patient $patient): bool
    {
        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        if (! $this->hasActiveMembership($user, $patient->organization_id)) {
            return false;
        }

        if (! $this->permissionChecker->can($user, PermissionKey::PatientsViewOwn, $patient->organization_id)) {
            return false;
        }

        $professional = Professional::query()
            ->where('organization_id', $patient->organization_id)
            ->where('user_id', $user->id)
            ->first();

        if ($professional === null) {
            return false;
        }

        if ($patient->primary_professional_id === $professional->id) {
            return true;
        }

        return $patient->appointments()->where('professional_id', $professional->id)->exists();
    }

    /**
     * Mesma definição de "acesso próprio" de `hasOwnAccess()`, mas sem um
     * `Patient` específico para checar `primary_professional_id` contra —
     * usada por `viewAny()` para liberar o profissional autoatendido a
     * abrir a busca de pacientes (`PatientController::search()`), que por
     * sua vez escopa os resultados ao vínculo profissional (nunca devolve
     * pacientes de outros profissionais).
     */
    private function hasOwnViewAnyAccess(User $user, string $organizationId): bool
    {
        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        if (! $this->hasActiveMembership($user, $organizationId)) {
            return false;
        }

        if (! $this->permissionChecker->can($user, PermissionKey::PatientsViewOwn, $organizationId)) {
            return false;
        }

        return Professional::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('status', RecordStatus::Active)
            ->exists();
    }

    private function hasActiveMembership(User $user, string $organizationId, bool $requireOwner = false): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $query = $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembershipStatus::Active);

        if ($requireOwner) {
            $query->where('is_owner', true);
        }

        return $query->exists();
    }
}
