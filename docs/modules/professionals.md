# Profissionais

## Modelo

`App\Models\Professional` — cadastro operacional próprio, **não** uma
especialização de `App\Models\User` nem de `App\Models\SiteProfessional`
(vitrine pública — ver [public-integration.md](public-integration.md)). O
vínculo com `User` (`user_id`) é opcional e nunca concede acesso ao
sistema por si só — acesso continua dependendo exclusivamente de
`OrganizationMembership`/papel/`PermissionChecker`. `document` guarda
apenas dígitos (mascarado nas listagens via `App\Support\Documents\Document`).

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
