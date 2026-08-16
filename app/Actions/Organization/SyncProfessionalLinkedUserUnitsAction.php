<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\RecordStatus;
use App\Models\OrganizationMembership;
use App\Models\Professional;

/**
 * Mantém o acesso por unidade (UnitMembership) do usuário vinculado a um
 * profissional sincronizado com as unidades operacionais do próprio
 * profissional (ProfessionalUnit). Sem isso, um profissional (ver
 * CreateProfessionalAction, que já provisiona o usuário de acesso) nunca
 * teria uma unidade ativa selecionável ao logar, mesmo depois de ser
 * atribuído a uma unidade pela tela "Unidades de atuação". Chamada sempre
 * que um vínculo de unidade do profissional muda (criado, removido,
 * ativado, desativado ou tem a unidade principal alterada).
 *
 * Nunca concede papel/permissão — reaproveita
 * App\Actions\Organization\AssignUserUnitsAction, a mesma lógica já usada
 * para o gerenciamento manual de unidades de qualquer usuário. Não faz
 * nada quando o profissional não tem usuário vinculado ou o vínculo não
 * tem membership ativo (nada a sincronizar).
 */
class SyncProfessionalLinkedUserUnitsAction
{
    public function __construct(private readonly AssignUserUnitsAction $assignUserUnitsAction) {}

    public function handle(Professional $professional): void
    {
        if ($professional->user_id === null) {
            return;
        }

        $membership = OrganizationMembership::query()
            ->where('organization_id', $professional->organization_id)
            ->where('user_id', $professional->user_id)
            ->where('status', OrganizationMembershipStatus::Active)
            ->first();

        if ($membership === null) {
            return;
        }

        $activeUnitLinks = $professional->unitLinks()->where('status', RecordStatus::Active)->get();

        $this->assignUserUnitsAction->handle(
            $membership,
            $activeUnitLinks->pluck('unit_id'),
            $activeUnitLinks->firstWhere('is_primary', true)?->unit_id,
        );
    }
}
