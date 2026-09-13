<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use Illuminate\Support\Facades\Log;

/**
 * Resolve "a" organização desta instalação single-tenant (ver
 * docs/decisions/ADR-010-single-tenant-install-and-seo.md) — usada por
 * todo o front público e pelo autocadastro do portal do paciente, que
 * nunca autenticam um usuário e por isso não têm um TenantContext próprio
 * (ver App\Support\Tenancy\TenantContext, exclusivo de rotas autenticadas)
 * para consultar.
 *
 * Uma instalação correta nunca tem mais de uma Organization cadastrada.
 * Um `Organization::query()->first()` sem `ORDER BY` não é seguro nesse
 * cenário inválido: o Postgres não garante ordem estável para uma consulta
 * sem ordenação assim que a tabela passa a ter mais de uma linha, então
 * requisições distintas podiam retornar organizações diferentes entre si
 * (ver o incidente que motivou esta classe: dados de instalação de
 * QA/teste convivendo na mesma base de uma instalação de demonstração).
 * Aqui a resolução é sempre determinística — a organização mais antiga —
 * e o estado inválido é registrado em log, já que ele nunca deveria
 * ocorrer numa instalação correta.
 */
final class CurrentInstallationOrganizationQuery
{
    public function resolve(): ?Organization
    {
        $organizations = Organization::query()->oldest()->limit(2)->get();

        if ($organizations->count() > 1) {
            Log::warning(
                'Mais de uma organização encontrada nesta instalação single-tenant (ADR-010) — usando a mais antiga de forma determinística.',
                ['organization_ids' => $organizations->pluck('id')->all()],
            );
        }

        return $organizations->first();
    }
}
