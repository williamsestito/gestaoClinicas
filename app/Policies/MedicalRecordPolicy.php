<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MedicalRecordStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\OrganizationMembership;
use App\Models\Patient;
use App\Models\User;

/**
 * Autorização de conteúdo clínico (Etapa 4, Seção 10 do documento de
 * visão). Desvio deliberado do padrão do resto do sistema: em nenhum
 * método aqui um `is_owner`/`is_platform_admin` libera acesso sozinho —
 * RN-015 ("administrador da plataforma não possui acesso clínico
 * automático") e RN-016 ("proprietário administrativo não possui acesso
 * clínico irrestrito somente por ser proprietário") proíbem exatamente o
 * atalho que `App\Support\Authorization\PermissionChecker::can()` sempre
 * concede (ver o próprio docblock dessa classe) — por isso esta Policy
 * nunca usa `PermissionChecker`, nem no caminho próprio nem no amplo,
 * resolvendo a permissão direto no papel atribuído ao vínculo. Ver também
 * `App\Enums\SystemRole::Owner::defaultPermissions()`, que exclui
 * explicitamente as duas permissões clínicas do conjunto padrão do
 * proprietário — do contrário todo proprietário passaria a ter acesso
 * clínico real (não um bypass, uma permissão de fato concedida ao papel),
 * o mesmo problema por uma porta diferente.
 */
class MedicalRecordPolicy
{
    public function create(User $user, Appointment $appointment): bool
    {
        return $this->hasClinicalAccess($user, $appointment->professional_id, $appointment->organization_id);
    }

    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        return $this->hasClinicalAccess($user, $medicalRecord->professional_id, $medicalRecord->organization_id);
    }

    public function update(User $user, MedicalRecord $medicalRecord): bool
    {
        return $medicalRecord->status === MedicalRecordStatus::Draft
            && $this->hasClinicalAccess($user, $medicalRecord->professional_id, $medicalRecord->organization_id);
    }

    public function finalize(User $user, MedicalRecord $medicalRecord): bool
    {
        return $medicalRecord->status === MedicalRecordStatus::Draft
            && $this->hasClinicalAccess($user, $medicalRecord->professional_id, $medicalRecord->organization_id);
    }

    public function release(User $user, MedicalRecord $medicalRecord): bool
    {
        return $medicalRecord->status === MedicalRecordStatus::Finalized
            && $this->hasClinicalAccess($user, $medicalRecord->professional_id, $medicalRecord->organization_id);
    }

    public function addAddendum(User $user, MedicalRecord $medicalRecord): bool
    {
        return $medicalRecord->status === MedicalRecordStatus::Finalized
            && $this->hasClinicalAccess($user, $medicalRecord->professional_id, $medicalRecord->organization_id);
    }

    public function manageFiles(User $user, MedicalRecord $medicalRecord): bool
    {
        return $this->hasClinicalAccess($user, $medicalRecord->professional_id, $medicalRecord->organization_id);
    }

    /**
     * Histórico de prontuários de um paciente (ver "Meus pacientes" →
     * histórico). Mesma regra de acesso, ancorada no
     * `primary_professional_id` do paciente em vez do autor de um registro
     * específico.
     */
    public function viewPatientHistory(User $user, Patient $patient): bool
    {
        return $this->hasClinicalAccess($user, $patient->primary_professional_id, $patient->organization_id);
    }

    /**
     * @param  string|null  $recordProfessionalId  Profissional "dono" do
     *                                             contexto (autor do registro, ou profissional principal do
     *                                             paciente para o histórico) — null quando o paciente não tem
     *                                             nenhum profissional vinculado ainda.
     */
    private function hasClinicalAccess(User $user, ?string $recordProfessionalId, string $organizationId): bool
    {
        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        $membership = $user->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationMembershipStatus::Active)
            ->first();

        if ($membership === null) {
            return false;
        }

        if ($recordProfessionalId !== null
            && $this->isLinkedProfessional($user, $recordProfessionalId)
            && $this->hasPermission($membership, PermissionKey::MedicalRecordsManageOwn)
        ) {
            return true;
        }

        return $this->hasPermission($membership, PermissionKey::MedicalRecordsManage);
    }

    /**
     * Nunca via `PermissionChecker` (ver docblock da classe) — só concede
     * se o papel atribuído ao vínculo realmente tiver a permissão, sem
     * nenhum atalho de owner/platform-admin. Usado pelos dois caminhos
     * (próprio e amplo) de `hasClinicalAccess()`.
     */
    private function hasPermission(OrganizationMembership $membership, PermissionKey $permission): bool
    {
        return $membership->role_id !== null
            && $membership->role->permissions()->where('key', $permission->value)->exists();
    }

    private function isLinkedProfessional(User $user, string $professionalId): bool
    {
        return $user->professionals()
            ->where('status', RecordStatus::Active)
            ->whereKey($professionalId)
            ->exists();
    }
}
