# Multiempresa (tenancy)

## Modelo

Banco de dados e schema **compartilhados** (ver [ADR-006](../decisions/ADR-006-shared-schema-tenancy.md)).
Toda tabela de negócio carrega `organization_id`; tabelas específicas de
unidade também carregam `unit_id`. Não há pacote externo de multi-tenancy
— o isolamento é feito por convenção de query + autorização, nunca por
Global Scope silencioso (que esconderia dados em comandos administrativos).

**Exceção documentada**: as tabelas do módulo "site da clínica"
(`site_settings`, `site_professionals`, `site_services` e demais coleções
promocionais) são singleton por instalação — sem `organization_id` (ver
[ADR-010](../decisions/ADR-010-single-tenant-install-and-seo.md)). Onde
essas tabelas referenciam um registro multiempresa (ex.: o vínculo opcional
`site_professionals.professional_id`), o isolamento não pode ser expresso
como FK composta e é revalidado no domínio contra
`TenantContext::organization()` — ver
[public-integration.md](../modules/public-integration.md).

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

`EnsureOrganizationMembership`/`EnsureUnitMembership`/`EnsureLegalEntityMembership`
protegem rotas com `{organization}`/`{unit}`/`{legalEntity}` no path:
comparam o registro resolvido pelo route model binding com o contexto
ativo, devolvendo 404 em caso de divergência — proteção contra IDOR (um
usuário não descobre nem acessa recursos de outra organização trocando o
ID na URL). `EnsureUnitMembership` vai além do org-match: valida também que
a unidade está ativa e que o usuário tem `UnitMembership` ativo
especificamente para ela — não basta pertencer à mesma organização.

`EnsureActiveUnit` bloqueia rotas operacionais (que dependem de uma
unidade, não só de uma organização) quando não há unidade ativa resolvida
ou quando ela deixou de estar ativa — limpa o contexto inválido e
redireciona para o seletor de unidade. Rotas só-organização (dados gerais
da clínica, entidades legais, criação/seleção de unidade) não exigem essa
unidade ativa; rotas operacionais (dashboard e módulos futuros) exigem.

`EnsureNoActiveOrganization` bloqueia o onboarding para quem já tem um
vínculo ativo com alguma organização — impede reacesso ao assistente e
criação ilimitada de clínicas pela mesma rota.

`EnsureUserIsActive` (middleware global do grupo `web`) bloqueia qualquer
requisição autenticada de um usuário com `is_active = false`: encerra a
sessão e redireciona para o login. `FortifyServiceProvider::authenticateUsing`
aplica o mesmo bloqueio no momento do login.

## Autorização de escrita e regra do último proprietário

Toda escrita em Organization/LegalEntity/Unit exige
`organization_membership.is_owner = true` (ver Policies). Não há papéis e
permissões granulares ainda. `App\Support\Tenancy\OwnershipGuard` centraliza
a regra "uma organização sempre precisa de ao menos um proprietário ativo":
usada por `DeactivateOrganizationMembershipAction` (não deixa inativar o
último proprietário), `RevokeUnitAccessAction` (não deixa o último
proprietário perder acesso à unidade matriz sem substituição) e
`RequestAccountClosureAction` (não deixa encerrar a conta do único
proprietário ativo de uma clínica).

## Contexto formatado para o frontend

`HandleInertiaRequests` compartilha um prop `tenant` (via
`App\Support\Tenancy\TenantContextPresenter`) com todo request Inertia:
organização/unidade ativas, membership, listas de organizações/unidades
disponíveis (só ativas, só com vínculo ativo do usuário) e `isOwner`/
`isUnitManager`. Nunca envia os Models completos — nunca CPF/CNPJ. É a
partir desse prop que o cabeçalho (`TenantSwitcher.vue`) mostra a
clínica/unidade ativa e os seletores (só exibidos quando há mais de uma
opção).

## Onde a decisão de guardar o contexto em sessão está documentada

Ver [ADR-007](../decisions/ADR-007-session-active-context.md).
