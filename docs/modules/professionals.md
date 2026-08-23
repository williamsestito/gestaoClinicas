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
futuro, e os campos opcionais na criação — data de nascimento, biografia,
nenhuma especialidade, nenhum registro profissional — nunca calculados a
partir de um flag salvo, sempre recomputados na hora, então o aviso
persiste até o cadastro ser de fato completado, por quem for). Exibido no
resumo da ficha do profissional (`ProfessionalOperationalSummary.vue`,
igual para admin e para o próprio profissional autoatendido) e na
listagem (`App\Queries\ProfessionalListQuery`, que recalcula o status a
partir dos mesmos agregados já carregados — nunca reinvocando o resolver
por linha, para não reintroduzir N+1 em escala de listagem).

## Visualização — exceção para profissionais autoatendidos

`ProfessionalPolicy::view()`/`viewAny()`: a regra geral é "qualquer membro
ativo pode ver o cadastro de qualquer profissional" (mesmo padrão de
`SpecialtyPolicy`/`ServicePolicy`/`ProfessionalRegistrationPolicy`, testado
em `tests/Feature/Professionals/PolicyTest.php`) — pensada para recepção/
administração precisarem consultar o cadastro de qualquer profissional
sem uma permissão dedicada. Exceção deliberada: um usuário que é ele mesmo
um profissional vinculado (`isLinkedProfessional()`) só enxerga a própria
ficha por essa via; para ver a de um colega, precisa de
`ProfessionalsView`/`ProfessionalsManage` ou ser proprietário. Sem essa
exceção, um profissional autoatendido (ver seção de provisionamento acima)
enxergaria unidades, jornada e ausências de qualquer colega só por estar
logado — a regra original nunca previu esse caso porque, antes do
autoatendimento existir, só recepção/administração faziam login.

## Dashboard do profissional

`App\Http\Controllers\Organization\DashboardController::index()` renderiza
o mesmo componente Inertia (`Dashboard`) para todo mundo, mas troca o
conteúdo: quando o papel ativo do usuário é exatamente
`SystemRole::Professional` (nunca proprietário/administrador, mesmo que
também tenham um cadastro de profissional vinculado — `resolveOwnDashboardProfessional()`)
e existe um `Professional` ativo vinculado, a prop `professionalDashboard`
vem preenchida e `resources/js/components/dashboard/ProfessionalDashboard.vue`
substitui a visão geral administrativa; senão, `professionalDashboard` é
`null` e o dashboard administrativo de sempre é exibido. Isso evita duas
rotas/páginas separadas para a mesma URL `/dashboard`.

Conteúdo do dashboard do profissional, tudo escopado ao próprio
profissional (nunca aceita `professional_id` do frontend, mesmo padrão de
`MyAppointmentRequestsController`/`MyScheduleController`):

- **Alerta de pré-agendamentos pendentes**: contagem + até 5 mais recentes
  com `status = Pending`, sempre visível ao carregar a página quando
  houver algum (não é um toast) — link para "Meus pré-agendamentos" para o
  gerenciamento completo.
- **Contadores "em aberto"/"agendados"/"executados"**: `Requested` +
  `AwaitingConfirmation`, `Confirmed` e `Completed` respectivamente,
  recalculados para o período selecionado (dia/semana/mês, ver abaixo) —
  não são contadores globais.
- **Prévia da agenda** com alternância dia/semana/mês
  (`period`/`date` na query string do próprio `/dashboard`, recarregados
  via Inertia partial reload `only: ['professionalDashboard']` — nunca
  refaz a consulta administrativa ao trocar de período). Semana =
  segunda a domingo (`Carbon::MONDAY`/`Carbon::SUNDAY`, independente de
  config de locale). Lista limitada a 200 agendamentos por período
  (`agendaTruncated` sinaliza quando o limite foi atingido).
- **Nunca mostra "Últimas atividades"** (log de auditoria) — decisão
  explícita do usuário: o dashboard do profissional é operacional, não
  administrativo.
- **Campo de data no card "Agenda"** (Etapa 3.7, `<input type="date">` ao
  lado de Anterior/Hoje/Próximo): pula direto para qualquer dia/semana/mês
  desejado, sem precisar andar um de cada vez — usa o mesmo `reload()` que
  os outros controles, então o backend recalcula o intervalo a partir de
  `referenceDate` (`DashboardController::periodRange()`) normalmente.
  Layout (Etapa 3.7): "Agenda" e "Avisos e lembretes" ficam lado a lado
  (`lg:grid-cols-3`, agenda ocupando 2/3), em vez de empilhados.
- **Lembretes tipo post-it** (`App\Models\ProfessionalDashboardReminder`,
  recurso novo): conteúdo pessoal do profissional, sem relevância de
  negócio (não é paciente/financeiro/clínico) — por isso é o único
  registro do módulo de profissionais que permite exclusão física e que
  não passa por `AuditLogger` (ver comentário na Action de criação).
  CRUD mínimo: criar e excluir (sem edição), autorização via FormRequest
  (`StoreDashboardReminderRequest`/`DestroyDashboardReminderRequest`),
  mesmo padrão de "autoatendimento sem Policy dedicada" já usado em
  `UpdateOwnAppointmentRequestStatusRequest`. Grade de 2 colunas; clicar
  num post-it abre um popup (`Dialog`) com o texto completo, com a ação
  de remover reaproveitada ali dentro — a exclusão pelo X do card
  continua funcionando igual (Etapa 3.7).
  - **Alarme** (Etapa 3.7, ex.: "tomar remédio às 12h"): campo opcional
    `alarm_at` (UTC), convertido a partir do horário local do navegador só
    no cliente (`Date::toISOString()`) — nunca depende de fuso do
    servidor para um dado puramente pessoal, e nunca faz parte da
    validação de conflito de agenda (não tem nenhuma relação com
    `Appointment`/`AppointmentOverlapGuard`). Conferido só no cliente, a
    cada 15s enquanto o dashboard estiver aberto (decisão explícita do
    usuário: sem push/Service Worker nesta fase — não dispara nada com a
    aba fechada). Ao bater a hora, abre um popup de alarme que só fecha
    pelo botão "Fechar alarme" (bloqueado contra Esc/clique fora), que
    silencia via `PATCH /dashboard/lembretes/{reminder}/silenciar-alarme`
    (`DismissProfessionalDashboardReminderAlarmAction`, mesmo padrão de
    autorização de `DestroyDashboardReminderRequest`) — só zera
    `alarm_at`, o post-it em si não é removido.

## Autoatendimento de agendamento (Etapa 3.7)

Além de gerenciar a própria ficha/jornada/ausências, o profissional pode
agir sobre os próprios atendimentos sem depender de `appointments.manage`
(permissão ampla de recepção/administração):

- **Converter o próprio pré-agendamento em agendamento real**: em "Meus
  pré-agendamentos", `App\Policies\AppointmentRequestPolicy::createFromOwnRequest()`
  autoriza a conversão só quando `AppointmentRequest.professional_id`
  aponta para um `Professional` ativo do próprio usuário — o Gate do
  Laravel resolve a policy pela classe do model passado a `can()`, por
  isso este método vive na policy de `AppointmentRequest`, não na de
  `Appointment`. `AppointmentController::store()` trava explicitamente
  que o `professional_id` enviado bate com o do pré-agendamento quando a
  autorização veio só por esse caminho — sem isso, um profissional
  poderia trocar `professional_id` no formulário e agendar na agenda de
  outro colega.
- **"Agendar" em um clique**, sem reabrir formulário: quando o
  pré-agendamento já carrega `unit_id` + `preferred_service_id` +
  `preferred_starts_at` (veio de um horário específico escolhido na busca
  de disponibilidade da landing — ver
  [public-integration.md](public-integration.md)), o botão "Agendar" abre
  um popup de confirmação em vez do formulário completo, postando direto
  em `POST /settings/appointments` (mesmo endpoint/Action de sempre).
  Pré-agendamentos sem esses três campos (leads antigos, ou enviados pelo
  formulário manual sem escolher horário específico) continuam caindo no
  formulário de conversão, com Unidade/Profissional travados como texto
  fixo quando já conhecidos.
- **Reagendar/cancelar o próprio atendimento**:
  `AppointmentPolicy::reschedule()`/`cancel()` ganharam o mesmo caminho
  `hasOwnAccess(..., PermissionKey::AppointmentsManageOwn)` já usado por
  `confirm()`/`manageStatus()` — escopado ao vínculo profissional/
  atendimento, nunca ao atendimento de um colega.

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
