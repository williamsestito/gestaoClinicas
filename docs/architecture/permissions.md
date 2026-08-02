# Papéis e permissões (RBAC)

Catálogo de permissões (`App\Enums\PermissionKey`) e matriz dos 7 papéis de
sistema (`App\Enums\SystemRole`, criados automaticamente para toda
organização nova — ver `App\Actions\Organization\SeedSystemRolesAction`).
Gerado a partir de `SystemRole::defaultPermissions()`; se um papel de
sistema ganhar/perder uma permissão no código, atualize esta tabela junto.

## Bypasses (fora da tabela abaixo)

- **Proprietário** (`OrganizationMembership.is_owner=true`): acesso total,
  independente de papel atribuído — nunca depende desta tabela.
- **Administrador da plataforma** (`User.is_platform_admin=true`): acesso
  total a qualquer organização/unidade via `PermissionChecker`, inclusive
  organizações às quais ainda não tinha vínculo — nesse caso, um
  `OrganizationMembership`/`UnitMembership` real é criado automaticamente
  no primeiro acesso (nunca `is_owner`), em vez do código tratar um estado
  "sem vínculo" espalhado pelas queries. Ver
  `SetActiveOrganizationAction`/`SetActiveUnitAction` e [tenancy.md](tenancy.md).
- Papéis personalizados (criados via `/roles`) não aparecem aqui — a tela
  de papéis permite compor qualquer combinação das mesmas permissões
  abaixo.

## Matriz (papéis de sistema, além de Proprietário)

`✓` = concedida por padrão. Proprietário tem todas (omitido da tabela por
ter sempre tudo).

| Permissão | Admin. clínica | Gerente unidade | Recepção | Profissional | Financeiro | Auditor |
|---|---|---|---|---|---|---|
| **Visão geral** | | | | | | |
| Visualizar o painel geral | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Dados da clínica** | | | | | | |
| Visualizar dados da clínica | ✓ | | | | | |
| Editar dados da clínica | ✓ | | | | | |
| **Unidades** | | | | | | |
| Visualizar unidades | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Criar unidades | ✓ | | | | | |
| Editar unidades | ✓ | ✓ | | | | |
| Ativar unidades | ✓ | ✓ | | | | |
| Inativar unidades | ✓ | ✓ | | | | |
| Excluir unidades | ✓ | | | | | |
| Restaurar unidades | ✓ | | | | | |
| Definir unidade matriz | ✓ | | | | | |
| **Entidades legais** | | | | | | |
| Visualizar entidades legais | ✓ | ✓ | ✓ | | ✓ | ✓ |
| Criar entidades legais | ✓ | | | | | |
| Editar entidades legais | ✓ | | | | | |
| Excluir entidades legais | ✓ | | | | | |
| Restaurar entidades legais | ✓ | | | | | |
| Definir entidade legal principal | ✓ | | | | | |
| **Usuários** | | | | | | |
| Visualizar usuários | ✓ | ✓ | | | | ✓ |
| Cadastrar usuários | ✓ | | | | | |
| Convidar usuários | ✓ | | | | | |
| Editar usuários | ✓ | | | | | |
| Ativar usuários | ✓ | | | | | |
| Inativar usuários | ✓ | | | | | |
| Atribuir papéis a usuários | ✓ | | | | | |
| Atribuir unidades a usuários | ✓ | | | | | |
| **Papéis e permissões** | | | | | | |
| Visualizar papéis e permissões | ✓ | | | | | |
| Criar papéis personalizados | ✓ | | | | | |
| Editar papéis personalizados | ✓ | | | | | |
| Excluir papéis personalizados | ✓ | | | | | |
| Atribuir permissões a papéis | ✓ | | | | | |
| **Site da clínica** | | | | | | |
| Visualizar o site da clínica | ✓ | ✓ | | | | ✓ |
| Editar o site da clínica | ✓ | | | | | |
| Publicar/despublicar o site | ✓ | | | | | |
| Visualizar solicitações de agendamento | ✓ | ✓ | ✓ | | | ✓ |
| Gerenciar solicitações de agendamento | ✓ | | ✓ | | | |
| **SEO e marketing** | | | | | | |
| Visualizar SEO e marketing | ✓ | ✓ | | | | ✓ |
| Editar SEO e marketing | ✓ | | | | | |
| **Auditoria** | | | | | | |
| Visualizar auditoria | ✓ | ✓ | | | | ✓ |
| **Configurações** | | | | | | |
| Visualizar configurações | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Editar configurações | ✓ | | | | | |
| **Especialidades** | | | | | | |
| Visualizar especialidades | ✓ | ✓ | | | | ✓ |
| Gerenciar especialidades | ✓ | | | | | |
| **Serviços** | | | | | | |
| Visualizar serviços | ✓ | ✓ | | | | ✓ |
| Gerenciar serviços | ✓ | | | | | |
| **Profissionais** | | | | | | |
| Visualizar profissionais | ✓ | ✓ | | | | ✓ |
| Gerenciar profissionais | ✓ | | | | | |
| Gerenciar especialidades do profissional | ✓ | | | | | |
| Gerenciar vínculos com unidades | ✓ | | | | | |
| Gerenciar vínculos com serviços | ✓ | | | | | |
| **Registros profissionais** | | | | | | |
| Visualizar registros profissionais | ✓ | ✓ | | | | ✓ |
| Gerenciar registros profissionais | ✓ | | | | | |
| Visualizar número completo do registro | ✓ | | | | | ✓ |
| **Jornada e disponibilidade** | | | | | | |
| Visualizar jornada e disponibilidade | ✓ | ✓ | | | | ✓ |
| Gerenciar jornada e disponibilidade | ✓ | ✓¹ | | | | |
| **Ausências e bloqueios** | | | | | | |
| Visualizar ausências e bloqueios | ✓ | ✓ | | | | ✓ |
| Gerenciar ausências e bloqueios | ✓ | ✓¹ | | | | |

¹ Gerente de unidade só gerencia jornada/bloqueios de profissionais em
unidades onde possui `UnitMembership.is_manager = true` — diferente das
demais permissões desta tabela (que hoje são sempre org-wide), esta é a
primeira permissão com escopo restrito a unidades específicas, verificado
em `App\Policies\ProfessionalPolicy::manageAvailability()`/
`manageTimeBlocks()` além da checagem usual de `PermissionChecker`.

## Onde isso é aplicado

- **Backend**: toda Policy consulta `App\Support\Authorization\PermissionChecker`
  para o `PermissionKey` correspondente — nunca compara `role.name`
  diretamente.
- **Frontend**: a tela `/settings/organization` usa `canUpdate` (calculado
  a partir de `OrganizationUpdate`) para habilitar/desabilitar o
  formulário — corrigido nesta fase (Etapa 0.9) para não depender apenas
  de `isOwner`, já que administradores da clínica (não-proprietários)
  também têm `OrganizationUpdate`.
- **Convite de usuário**: `UsersInvite` controla quem pode convidar; o
  papel atribuído ao convidado determina as permissões dele a partir desta
  mesma tabela.

## Personalização

Um papel personalizado pode combinar qualquer subconjunto das permissões
acima (tela `/roles`); não há hierarquia implícita entre papéis — todas as
combinações são explícitas por `PermissionKey`.
