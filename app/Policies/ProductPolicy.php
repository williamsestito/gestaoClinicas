<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;

/**
 * Mesmo padrão de `ServicePolicy`: visualização liberada para qualquer
 * membro ativo da organização; gestão exige o proprietário ou a permissão
 * granular via papel atribuído. Dados comerciais não são clínicos — sem
 * nenhum desvio de RN-015/016 aqui.
 */
class ProductPolicy
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->hasActiveMembership($user, $product->organization_id);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->hasActiveMembership($user, $organization->id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProductsManage, $organization->id);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->hasActiveMembership($user, $product->organization_id, requireOwner: true)
            || $this->permissionChecker->can($user, PermissionKey::ProductsManage, $product->organization_id);
    }

    public function activate(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function deactivate(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->update($user, $product);
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
