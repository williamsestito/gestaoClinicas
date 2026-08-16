# Profissionais

## Modelo

`App\Models\Professional` — cadastro operacional próprio, **não** uma
especialização de `App\Models\User` nem de `App\Models\SiteProfessional`
(vitrine pública — ver [public-integration.md](public-integration.md)). O
vínculo com `User` (`user_id`) em si nunca concede acesso ao sistema por
si só — acesso continua dependendo exclusivamente de
`OrganizationMembership`/papel/`PermissionChecker`. `document` guarda
apenas dígitos (mascarado nas listagens via `App\Support\Documents\Document`).

### Provisionamento de acesso na criação — exceção deliberada

Diferente do vínculo manual descrito acima, `CreateProfessionalAction`
**sempre** provisiona um `User` de acesso próprio junto com o profissional
(papel de sistema "Profissional", `OrganizationMembership` ativo), com a
senha definida diretamente pelo administrador no formulário de criação —
CPF e e-mail passam a ser obrigatórios nesse fluxo. Isso é uma exceção
deliberada, registrada explicitamente por pedido do proprietário do
produto, à regra geral do restante da aplicação de nunca deixar um
administrador definir a senha de outro usuário (ver
`App\Actions\Organization\InviteUserAction`) — os demais fluxos de criação
de usuário continuam exigindo convite por e-mail. `ProfessionalController::resetUserPassword`
(tela de edição, seção "Usuário vinculado") estende a mesma exceção para
permitir redefinir essa senha depois.

O vínculo manual a um usuário já existente (`LinkProfessionalUserAction`,
via "Vincular a um usuário existente" na tela de edição) continua sendo
puramente informativo, sem conceder papel/membership/acesso a unidade —
só o fluxo de criação tem esse comportamento.

### Sincronização de unidades do usuário vinculado

`App\Actions\Organization\SyncProfessionalLinkedUserUnitsAction` mantém o
acesso por unidade (`UnitMembership`) do usuário vinculado sincronizado
com as unidades operacionais do profissional (`ProfessionalUnit`) —
chamada por `AssignProfessionalToUnitAction`/`RemoveProfessionalFromUnitAction`/
`ActivateProfessionalUnitAction`/`DeactivateProfessionalUnitAction`/
`SetPrimaryProfessionalUnitAction` sempre que um vínculo de unidade muda.
Sem isso, um profissional recém-criado nunca teria uma unidade ativa
selecionável ao logar, mesmo depois de atribuído a uma unidade — reaproveita
`AssignUserUnitsAction` (a mesma lógica já usada para o gerenciamento
manual de unidades de qualquer usuário), nunca concede papel/permissão.
Não se aplica ao vínculo manual via `LinkProfessionalUserAction` (mantém-se
puramente informativo).

## Vínculos

- `unitLinks()` (`ProfessionalUnit`) — unidades onde o profissional atua,
  com vínculo "principal". `professional_unit` tem unique composto
  `(organization_id, id)`, necessário para as FKs compostas de
  `professional_working_hours`.
- `specialtyLinks()` (`ProfessionalSpecialty`) — especialidades do
  profissional.
- `serviceLinks()` (`ProfessionalService`) — serviços que executa, com
  possível sobrescrita por unidade.
- `registrations()` — ver [professional-registrations.md](professional-registrations.md).
- `workingHours()`/`timeBlocks()` — ver [availability.md](availability.md).

## Situação operacional

`App\Services\Professionals\ProfessionalOperationalStatusResolver` resolve
um status de 3 estados (`Operational`/`Incomplete`/`Inactive`) mais listas
de `reasons` (bloqueadores: profissional inativo, sem unidade ativa, sem
jornada configurada) e `warnings` (não bloqueadores: sem serviço ativo,
registro principal vencido, ausência em andamento, vínculo de unidade
futuro). Exibido no resumo da ficha do profissional
(`ProfessionalOperationalSummary.vue`) e na listagem
(`App\Queries\ProfessionalListQuery`, que recalcula o status a partir dos
mesmos agregados já carregados — nunca reinvocando o resolver por linha,
para não reintroduzir N+1 em escala de listagem).

## Criação, edição, ativação e exclusão

`/settings/professionals`, restrito por `PermissionKey::ProfessionalsManage`.
`DeleteProfessionalAction` nunca apaga o `User` vinculado, o
`SiteProfessional` promocional, memberships ou a foto — apenas marca o
cadastro operacional como excluído. `RestoreProfessionalAction` revalida
conflito de documento e remove silenciosamente o vínculo com `User` se ele
deixou de ser elegível nesse meio-tempo.

## Filtros da listagem

`App\Queries\ProfessionalListQuery`: status, unidade, especialidade,
serviço, com/sem jornada, com/sem unidade ativa, com/sem ausência em
andamento, situação operacional, excluídos.

## Filament

`App\Filament\Resources\Professionals\ProfessionalResource` — administra
os campos próprios do profissional (nome, nome social, nome de exibição,
contato, documento, biografia); vínculos com unidade/especialidade/
serviço/registros/jornada/ausências continuam geridos pelas telas Inertia
dedicadas (evita duplicar aquelas regras no Filament). A tabela mascara o
documento; a ficha de visualização mascara documento e telefone. Ações
Ativar/Inativar/Excluir/Restaurar chamam as Actions de domínio.
