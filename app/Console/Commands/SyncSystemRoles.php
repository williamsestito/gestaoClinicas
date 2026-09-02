<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Organization\SeedSystemRolesAction;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * `SeedSystemRolesAction` só é chamada automaticamente no onboarding de
 * uma organização nova — quando uma `PermissionKey` nova entra no
 * conjunto padrão de um papel de sistema já existente (ex.:
 * `MedicalRecordsManageOwn` no papel "Profissional", Etapa 4), nenhuma
 * organização criada antes disso recebe a permissão até este comando
 * rodar. Idempotente e puramente aditivo (`syncWithoutDetaching`) — nunca
 * remove uma permissão já concedida, nem uma customização feita pelo
 * administrador.
 */
#[Signature('app:sync-system-roles')]
#[Description('Sincroniza os papéis de sistema de todas as organizações com o conjunto padrão de permissões atual')]
class SyncSystemRoles extends Command
{
    public function handle(SeedSystemRolesAction $seedSystemRoles): int
    {
        $organizations = Organization::query()->get();

        $this->withProgressBar($organizations, function (Organization $organization) use ($seedSystemRoles): void {
            $seedSystemRoles->handle($organization);
        });

        $this->newLine(2);
        $this->info("Papéis de sistema sincronizados em {$organizations->count()} organização(ões).");

        return self::SUCCESS;
    }
}
