# Organizações

## Modelo

`App\Models\Organization` — ULID, `slug` único, `status`
(`active`/`inactive`/`suspended`), `default_timezone`, `default_currency`,
`locale`, cores de marca opcionais. Nunca é excluída fisicamente.

## Criação

Organizações só nascem pelo onboarding
(`App\Actions\Organization\OnboardOrganizationAction`), que cria em uma
única transação: organização, entidade legal principal, unidade matriz
(com endereço e horários) e o vínculo de proprietário do usuário logado.
Não há criação avulsa — nem via Inertia, nem via Filament
(`OrganizationResource::canCreate()` retorna `false`).

## Edição

- Inertia: `/settings/organization` — nome, fuso, moeda, locale e cores.
  Restrito a `organization_membership.is_owner = true`
  (`App\Policies\OrganizationPolicy`).
- Filament: `App\Filament\Resources\Organizations\OrganizationResource`
  (mesmos campos, mais ações "Ativar"/"Suspender" que reutilizam
  `ChangeOrganizationStatusAction`). Sem exclusão.

## Contexto ativo

Ver [docs/architecture/tenancy.md](../architecture/tenancy.md).
