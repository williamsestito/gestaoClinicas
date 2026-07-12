# Multiempresa (tenancy)

## Modelo

Banco de dados e schema **compartilhados** (ver [ADR-006](../decisions/ADR-006-shared-schema-tenancy.md)).
Toda tabela de negócio carrega `organization_id`; tabelas específicas de
unidade também carregam `unit_id`. Não há pacote externo de multi-tenancy
— o isolamento é feito por convenção de query + autorização, nunca por
Global Scope silencioso (que esconderia dados em comandos administrativos).

## Contexto ativo

`App\Support\Tenancy\TenantContext` (singleton por requisição) expõe a
organização, unidade, membership e unit membership ativos. É resolvido a
cada requisição por dois middlewares, nesta ordem:

1. `ResolveOrganizationContext` — lê `active_organization_id` da sessão,
   valida contra os vínculos reais do usuário (nunca confia apenas no ID
   salvo) e seleciona automaticamente se houver só uma organização.
2. `ResolveUnitContext` — mesma lógica, para `active_unit_id`, restrita às
   unidades da organização já resolvida.

`EnsureActiveOrganization` bloqueia rotas que exigem organização ativa:
redireciona para o onboarding (zero vínculos) ou para o seletor
(`/context/organization`, múltiplos vínculos sem seleção), e retorna 403
se a organização estiver suspensa/inativa.

`EnsureOrganizationMembership`/`EnsureUnitMembership` protegem rotas com
`{organization}`/`{unit}` no path: comparam o registro resolvido pelo route
model binding com o contexto ativo, devolvendo 404 em caso de divergência
— proteção contra IDOR (um usuário não descobre nem acessa recursos de
outra organização trocando o ID na URL).

## Autorização de escrita

Nesta fase, toda escrita em Organization/LegalEntity/Unit exige
`organization_membership.is_owner = true` (ver Policies). Não há papéis e
permissões granulares ainda.

## Onde a decisão de guardar o contexto em sessão está documentada

Ver [ADR-007](../decisions/ADR-007-session-active-context.md).
