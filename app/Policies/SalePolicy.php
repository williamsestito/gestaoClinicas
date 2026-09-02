<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Enums\SaleStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Dados comerciais não são clínicos — sem nenhum desvio de RN-015/016
 * aqui, diferente de `MedicalRecordPolicy`. Mesmo padrão amplo/próprio de
 * `PatientPolicy`/`AppointmentPolicy`.
 */
class SalePolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->hasBroadAccess($user, $organization->id, PermissionKey::SalesView)
            || $this->permissionChecker->can($user, PermissionKey::SalesManageOwn, $organization->id);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->hasBroadAccess($user, $sale->organization_id, PermissionKey::SalesView)
            || $this->hasOwnAccess($user, $sale);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasBroadAccess($user, $organization->id, PermissionKey::SalesManage)
            || $this->permissionChecker->can($user, PermissionKey::SalesManageOwn, $organization->id);
    }

    public function update(User $user, Sale $sale): bool
    {
        return $sale->status === SaleStatus::Draft || $sale->status === SaleStatus::PendingApproval
            ? $this->hasManageAccess($user, $sale)
            : false;
    }

    public function confirm(User $user, Sale $sale): bool
    {
        return $this->hasManageAccess($user, $sale);
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $sale->status === SaleStatus::Confirmed && $this->hasManageAccess($user, $sale);
    }

    public function approveDiscount(User $user, Sale $sale): bool
    {
        return $this->hasBroadAccess($user, $sale->organization_id, PermissionKey::SalesApproveDiscount);
    }

    private function hasManageAccess(User $user, Sale $sale): bool
    {
        return $this->hasBroadAccess($user, $sale->organization_id, PermissionKey::SalesManage)
            || $this->hasOwnAccess($user, $sale);
    }

    private function hasBroadAccess(User $user, string $organizationId, PermissionKey $permission): bool
    {
        return $this->hasActiveMembership($user, $organizationId, requireOwner: true)
            || $this->permissionChecker->can($user, $permission, $organizationId);
    }

    /**
     * Autoatendimento do profissional vinculado como
     * `patient.primary_professional_id` — mesma definição de "próprio
     * paciente" já usada em `PatientPolicy`/`AppointmentPolicy`.
     */
    private function hasOwnAccess(User $user, Sale $sale): bool
    {
        $patient = $sale->relationLoaded('patient') ? $sale->patient : Patient::query()->find($sale->patient_id);

        if ($patient === null || $patient->primary_professional_id === null) {
            return false;
        }

        $professional = Professional::query()->find($patient->primary_professional_id);

        if (! $professional || $professional->user_id === null || $professional->user_id !== $user->id) {
            return false;
        }

        if (! $user->is_active || $user->email_verified_at === null) {
            return false;
        }

        if (! $this->hasActiveMembership($user, $sale->organization_id)) {
            return false;
        }

        return $this->permissionChecker->can($user, PermissionKey::SalesManageOwn, $sale->organization_id);
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
