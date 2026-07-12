<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Unit;
use App\Models\UnitMembership;

/**
 * Contexto ativo de organização/unidade para a requisição atual. Resolvido
 * pelos middlewares ResolveOrganizationContext/ResolveUnitContext e
 * consultado pelo resto da aplicação (controllers, Actions, Inertia) através
 * de um único ponto — nunca confie diretamente em IDs vindos do frontend.
 */
class TenantContext
{
    private ?Organization $organization = null;

    private ?OrganizationMembership $membership = null;

    private ?Unit $unit = null;

    private ?UnitMembership $unitMembership = null;

    public function setOrganization(?Organization $organization, ?OrganizationMembership $membership): void
    {
        $this->organization = $organization;
        $this->membership = $membership;
    }

    public function setUnit(?Unit $unit, ?UnitMembership $unitMembership): void
    {
        $this->unit = $unit;
        $this->unitMembership = $unitMembership;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function membership(): ?OrganizationMembership
    {
        return $this->membership;
    }

    public function unit(): ?Unit
    {
        return $this->unit;
    }

    public function unitMembership(): ?UnitMembership
    {
        return $this->unitMembership;
    }

    public function hasOrganization(): bool
    {
        return $this->organization !== null;
    }

    public function hasUnit(): bool
    {
        return $this->unit !== null;
    }
}
