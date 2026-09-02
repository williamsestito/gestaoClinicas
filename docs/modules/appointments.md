# Agenda real (Appointment)

## Dois mundos, agora conectados

Antes desta etapa existiam dois sistemas paralelos sem relação: a
disponibilidade (`App\Services\Availability\ProfessionalAvailabilityResolver`,
puramente de leitura, nunca persiste nada) e `App\Models\AppointmentRequest`
(lead de contato — nome/telefone soltos, sem profissional nem horário
exato, sem checagem de conflito). `App\Models\Appointment` é a peça que
faltava: uma reserva de verdade, vinculada a `Patient`/`Professional`/
`Unit`/`Service`, com conflito checado de fato. `AppointmentRequest`
continua existindo sem alteração — é o funil de leads do site público;
converter um lead em `Appointment` fica para quando o booking público
existir (Etapa 3.2).

## `starts_at`/`ends_at` são instantes UTC — cuidado ao construir

Mesmo padrão de `ProfessionalTimeBlock` (evento datado), não de
`ProfessionalWorkingHour` (regra recorrente em hora civil). **Armadilha
real que já causou um bug nesta etapa**: o Eloquent serializa um `Carbon`
para a coluna `timestamp` usando o fuso em que o objeto *já está*, sem
converter sozinho. `Carbon::parse('09:00', 'America/Sao_Paulo')` produz o
instante certo (12:00 UTC) **enquanto o objeto existir em memória**, mas se
ele for persistido sem `->utc()` explícito, o Eloquent grava os números
"09:00" literalmente — perdendo a informação de fuso. Toda leitura
subsequente então interpreta "09:00" como UTC, resultando num agendamento
3h errado. `AppointmentController::store()`/`reschedule()` sempre fazem
`Carbon::parse($input, $unit->timezone)->utc()` — os dois passos são
obrigatórios, nunca só um. O mesmo cuidado vale para qualquer teste que
crie um `Appointment` diretamente via factory com um horário "local"
específico (ver `createConfirmedAppointment()` em
`tests/Feature/Appointments/AppointmentLifecycleTest.php`).

## Conflito: dois guards diferentes, um objetivo

`App\Support\Availability\AppointmentOverlapGuard` tem dois métodos:

- `assertWithinAvailability()` — reaproveita `ProfessionalAvailabilityResolver`
  sem alteração; o intervalo pedido precisa caber dentro de um dos
  `effectiveIntervals` do dia (jornada regular menos ausências/bloqueios).
- `assertNoConflict()` — verifica sobreposição contra outros agendamentos
  **não cancelados** do mesmo profissional. Usa
  `pg_advisory_xact_lock(crc32($professional->id))` em vez de
  `SELECT ... FOR UPDATE` sobre linhas existentes — um `FOR UPDATE` não
  trava nada quando o profissional ainda não tem nenhum agendamento,
  deixando uma janela de corrida para o *primeiro* agendamento dele
  (achado do security-review desta etapa, corrigido no mesmo dia). O
  advisory lock serializa independentemente de já existir linha, e é
  liberado automaticamente no fim da transação (`DB::transaction()`, já
  usado por `Create`/`RescheduleAppointmentAction`).

Ambos são sempre revalidados na Action, nunca confiando no horário
sugerido por `App\Services\Availability\StaffAppointmentSlotFinder`
(usado só para a UI sugerir horários livres).

## Sem "encaixe" configurável, sem recursos (salas/equipamentos)

Sobreposição é sempre bloqueada nesta etapa — o PDF de visão cita
permitir/proibir encaixe como opção configurável por clínica, mas não
existe tela de configuração de agenda ainda. Recursos compartilhados
(salas, equipamentos) não têm nenhuma base no código — nem model, nem
migration, em lugar nenhum — e ficam para a Etapa 3.2, quando o núcleo de
agendamento já estiver validado em uso real.

## Reagendamento sem auto-referência

Diferente do que se poderia esperar, reagendar **não** cria uma nova linha
nem mantém um ponteiro para o agendamento "anterior" — `RescheduleAppointmentAction`
atualiza `starts_at`/`ends_at` da própria linha e audita via `AuditLogger`
(before/after), o mesmo mecanismo de histórico usado por qualquer outra
alteração do projeto. Decisão deliberada para não reintroduzir a
complexidade de auto-referência que já foi evitada em Paciente (ver
`docs/modules/patients.md`).

## Autorização em 3 camadas

`AppointmentPolicy` seguindo o padrão de `ProfessionalPolicy`/`PatientPolicy`:
- Amplo: owner ou `appointments.manage` — único caminho para criar,
  reagendar ou cancelar.
- Próprio: `confirm()`/`manageStatus()` (confirmar/check-in/início/conclusão/
  não comparecimento) também aceitam o profissional vinculado ao
  agendamento (via `professional.user_id`) com a permissão granular
  `appointments.manage-own` — nunca o vínculo sozinho, sempre com
  membership ativo e permissão explícita. Este papel nunca pode criar/
  reagendar/cancelar. `confirm()` passou a aceitar "próprio" nesta etapa
  (antes era só amplo): o profissional confirmando o próprio agendamento
  em `Requested`/`AwaitingConfirmation` é o gatilho para o paciente ver a
  mudança refletida no portal, sem nenhuma sincronização adicional — os
  dois lados leem a mesma linha de `Appointment`. Exposto na UI via
  `resources/js/components/dashboard/ProfessionalDashboard.vue` (botões
  contextuais por status na prévia da agenda), reaproveitando as mesmas
  rotas/Actions do staff (`ConfirmAppointmentAction`, `CheckInAppointmentAction`,
  `StartAppointmentAction`, `CompleteAppointmentAction`, `MarkAppointmentNoShowAction`).

`ClinicAdmin`+`Reception` recebem `Manage`; `UnitManager`/`Auditor`
recebem `View`; `Professional` recebe `ViewOwn`+`ManageOwn`.

## Busca de paciente para agendar

`PatientController::search()` (novo endpoint, complementa o módulo de
Pacientes da Etapa 2.1) — busca por nome/CPF parcial para o autocomplete
de seleção de paciente na criação de agendamento. Diferente de
`duplicates()` (exige nome+nascimento combinados para achar duplicidade),
aqui basta um texto parcial. Nunca devolve o documento na resposta — só
id/nome/data de nascimento, suficiente para reconhecimento visual.

## Fora do escopo desta etapa (3.1)

Recursos compartilhados (salas/equipamentos) e seu conflito próprio,
booking público (depende de login do paciente, Etapa 2.2), conversão de
`AppointmentRequest` em `Appointment` com um clique, pacotes de sessões,
recorrência, lista de espera — ver `docs/roadmap.md`, Etapa 3.2.

## Etapa 3.2 — Booking público entra em `Requested`, nunca `Confirmed`

`App\Actions\PatientPortal\BookAppointmentAction` (guard `patient`, ver
`docs/modules/patient-portal.md`) reaproveita
`App\Support\Availability\AppointmentOverlapGuard` **sem alteração** — os
dois métodos (`assertWithinAvailability`/`assertNoConflict`) já bloqueiam
por `Requested/AwaitingConfirmation/Confirmed/CheckedIn/InProgress`, então
dois pacientes não conseguem reservar o mesmo horário mesmo antes de
qualquer confirmação da recepção. Diferente da criação pelo staff (sempre
`Confirmed`), o agendamento criado pelo paciente entra em `Requested` — o
primeiro consumidor real desse status, reservado desde a Etapa 3.1
especificamente para isso (ver docblock de `App\Enums\AppointmentStatus`).
`AwaitingConfirmation` continua sem uso: fica disponível, sem migração
nova, para um futuro fluxo onde a recepção propõe outro horário ao
paciente antes de confirmar.

`App\Actions\Organization\ConfirmAppointmentAction` (staff, permissão
`appointments.manage`, mesma política de `create`/`reschedule`/`cancel`)
transiciona `Requested` → `Confirmed`; exige explicitamente que o status
atual seja `Requested`. Recusar uma solicitação reaproveita
`CancelAppointmentAction` sem alteração — ele já aceita cancelar qualquer
status não final, `Requested` incluso.

`AuditLog.actor_user_id` é FK só para `App\Models\User` (staff) — como quem
criou o agendamento pelo portal foi um `App\Models\PatientUser`, o vínculo
(`booked_by: 'patient_portal'`, `patient_user_id`) vai dentro do `after`
(jsonb), não no schema da auditoria.

Nova classe compartilhada `App\Support\Availability\ActiveProfessionalServiceLinkResolver`
extrai a validação de vínculo profissional/serviço/unidade ativo antes
duplicada implicitamente em `AppointmentController::resolveActiveLink()` —
usada agora tanto pela criação do staff quanto pelo booking do portal
(`App\Http\Controllers\PatientPortal\PatientAppointmentController`).

**Fora desta etapa (decisão registrada)**: paciente cancelar/reagendar pelo
portal, uso de `AwaitingConfirmation`, notificação automática ao paciente
quando confirmado/recusado, recursos compartilhados (salas/equipamentos) —
ver `docs/roadmap.md`, Etapa 3.3.

## Etapa 3.2 — Conversão de lead em agendamento é um fluxo assistido, não automático

`App\Models\AppointmentRequest.service_id` é FK para `site_services`
(promocional), não para `services` (operacional) — não existe forma de
mapear automaticamente um lead para profissional/serviço/unidade
operacionais. A conversão (botão "Converter em agendamento" em
`settings/site/appointment-requests/Index.vue`, visível apenas para
`Pending`/`Contacted` e para quem tem `appointments.manage`) abre o
formulário de criação de agendamento já existente
(`settings/appointments/Create.vue`) com nome/telefone/observações do lead
pré-preenchidos — a recepção ainda busca ou cadastra o `Patient` real e
completa unidade/profissional/serviço/horário normalmente. Nenhum `Patient`
é criado automaticamente (o lead não tem data de nascimento, campo
obrigatório do cadastro de paciente).

`App\Actions\Organization\CreateAppointmentAction` ganhou um parâmetro
opcional `?AppointmentRequest $sourceRequest` — reaproveitada em vez de
duplicada. Quando presente, na mesma transação da criação do agendamento:
valida que o lead pertence à organização ativa, que ainda não foi
convertido (`appointment_id === null`, idempotência — nunca converte o
mesmo lead duas vezes) e que não está `Cancelled`; depois marca
`status = Scheduled` e grava `appointment_id` (nova coluna, FK simples
nullable, mesmo padrão de vínculo de `site_professionals.professional_id`
— não FK composta). Qualquer uma dessas validações falhando desfaz também
a criação do `Appointment`, já que tudo roda na mesma `DB::transaction()`.

Depois da conversão, `App\Http\Controllers\Organization\MyAppointmentRequestsController::index()`
carrega `appointment:id,status` junto com o lead e expõe
`appointment_status`/`appointment_status_label` em "Meus pré-agendamentos"
— lido direto do `Appointment` vinculado a cada requisição, então qualquer
confirmação/check-in/conclusão feita depois (pelo próprio profissional, via
`confirm()`, ou por staff) aparece ali na próxima vez que a tela recarrega,
sem sincronizar nada manualmente. O status do lead (`AppointmentRequestStatus`)
e o status do agendamento real (`AppointmentStatus`) continuam sendo dois
campos independentes — o segundo só existe quando `appointment_id` está
preenchido.

**Fora desta etapa (decisão registrada)**: criação automática de `Patient`
a partir dos dados do lead, mapeamento automático `SiteService` → `Service`
operacional, conversão em lote.

## Etapa 3.3 — Recursos compartilhados (salas/equipamentos)

Ver `docs/modules/resources.md` para o módulo completo. Aqui, só o que
mudou em `Appointment`: `resources()` (`BelongsToMany`, pivô
`appointment_resource`) permite 0-N recursos por agendamento.
`CreateAppointmentAction`/`RescheduleAppointmentAction` revalidam o
conflito de cada recurso vinculado
(`App\Support\Availability\ResourceOverlapGuard`) na mesma transação do
conflito de profissional — **nunca** dispensado pelo toggle de "encaixe"
abaixo.

## Etapa 3.3 — "Encaixe" configurável (sobreposição de agenda)

**Decisão MVP inventada** (nenhuma regra detalhada existia em lugar nenhum
do repositório para isso, só uma linha no roadmap): toggle booleano por
organização (`Organization.allow_appointment_overlap`), tudo-ou-nada —
quando ativado, `AppointmentOverlapGuard::assertNoConflict()` deixa de
lançar `ValidationException` em caso de sobreposição de **profissional**,
mas audita cada ocorrência via `AuditAction::ConflictDetected` (case já
existia desde antes, nunca usado até aqui). O método passou de `void` para
`bool` (retorna se havia conflito) — chamadas existentes que não passam
`allowOverlap` mantêm o comportamento de sempre bloquear, sem mudança.

Conflito de **recurso** nunca é dispensado por este toggle (ver
`docs/modules/resources.md`) — só a agenda humana do profissional admite
encaixe. Granularidade por serviço/profissional (em vez de tudo-ou-nada
por organização) fica para uma etapa futura.

UI: toggle em `settings/Organization.vue` ("Permitir encaixe na agenda"),
via `UpdateOrganizationAction`/`UpdateOrganizationRequest` já existentes —
campo `sometimes` (não `required`): ausente, o valor atual é preservado
(`collect($attributes)->only(...)` nunca zera uma chave ausente).

## Etapa 3.3 — Pacotes de sessões

**Decisão MVP inventada**: pacote (`App\Models\SessionPackage`) é só
contagem de sessões, sem preço/pagamento (Comercial/Financeiro ainda não
existem — Etapas 5/6; ligar a cobrança fica para quando esse módulo
existir). Pertence a um `Patient`, opcionalmente escopado a um `Service`
(nulo = qualquer serviço desconta dele). "Restantes"
(`remainingSessions()`) nunca é uma coluna persistida — sempre
`total_sessions - agendamentos Completed vinculados`, para nunca
dessincronizar.

`appointments.session_package_id` (nullable, `nullOnDelete` — perder o
pacote nunca apaga o histórico do atendimento).
`CreateAppointmentAction::assertSessionPackageUsable()` valida, na mesma
transação: pacote pertence ao mesmo paciente/organização, está `Active`,
não expirado (`expires_at`), tem `remainingSessions() > 0`. Gerenciado na
própria tela de cadastro do paciente (`settings/patients/Edit.vue`, seção
"Pacotes de sessões") — sem tela própria, reaproveita a permissão
`patients.manage` (`PatientPolicy::update`) em vez de criar uma nova.

## Etapa 3.3 — Recorrência semanal

**Decisão MVP inventada**: só recorrência semanal (mesmo dia da semana/
horário), sem RRULE genérico — nenhuma regra escrita existia para isso.
`App\Actions\Organization\CreateRecurringAppointmentSeriesAction` cria N
`Appointment` independentes de uma vez (sem "regra viva" que gera
ocorrências futuras) chamando `CreateAppointmentAction::handle()` em loop
— reaproveita tudo (guard, auditoria, pacote/recurso opcionais). Cada
ocorrência já nasce como uma linha normal, sem auto-referência entre si —
só `recurrence_group_id` (ULID comum, sem FK, só agrupador visual) as liga.
Limite de 52 ocorrências. Uma ocorrência que conflita (profissional ou
recurso) é **pulada**, sem abortar as demais — a resposta relata quantas
foram criadas e quais datas falharam.

## Etapa 3.3 — Lista de espera

**Decisão MVP inventada**: sem motor de notificação automática (exigiria
job agendado + canal de notificação — escopo de etapa futura, mesma
decisão já tomada para "notificar paciente quando confirmado" na 3.2).
`App\Models\WaitlistEntry` é um registro manual que a recepção consulta
(`settings/appointments/waitlist`) e converte à mão — mesmo padrão de
"conversão assistida" da Etapa 3.2 para `AppointmentRequest`, reaproveitado
sem unificar os dois em uma interface comum (dois usos concretos não
justificam abstração especulativa): `CreateAppointmentAction` ganhou um
terceiro parâmetro opcional `?WaitlistEntry $sourceWaitlistEntry`, tratado
por `convertSourceWaitlistEntry()` — idêntico em espírito a
`convertSourceRequest()`. `professional_id` nulo é uma opção válida
("qualquer profissional disponível"). Reaproveita a permissão
`appointments.manage` (via `AppointmentPolicy::create()`) — não cria
permissão nova.

## Etapa 3.3 — Portal: cancelar/reagendar + `AwaitingConfirmation` em uso

Paciente cancela/reagenda o próprio agendamento — `App\Actions\PatientPortal\CancelPatientAppointmentAction`/
`ReschedulePatientAppointmentAction`, mesmo formato das Actions de staff,
mas **não** as reaproveitando diretamente (não há onde registrar autoria
de um `PatientUser` nelas) — mesma convenção de `BookAppointmentAction`
(autoria no `after` do audit log). **Decisão MVP inventada**: sem prazo
mínimo de antecedência (nenhuma regra escrita existia para isso). Primeira
vez que o portal escopa dois níveis (`{patient}` e `{appointment}`):
`$patientUser->patients()->findOrFail($patient)->appointments()->findOrFail($appointment)`
— 404 se o agendamento não pertence a esse paciente específico.

`AwaitingConfirmation` (reservado desde a Etapa 3.1, nunca usado até aqui)
ganha seu primeiro fluxo real: staff propõe outro horário para um
`Requested` (`App\Actions\Organization\ProposeAlternateTimeAction`,
`appointments.manage`, revalida o guard para o novo horário); o paciente
aceita (`App\Actions\PatientPortal\AcceptProposedAppointmentTimeAction`,
`AwaitingConfirmation` → `Confirmed`) ou recusa (reaproveita
`CancelPatientAppointmentAction` sem alteração, motivo fixo "Horário
proposto recusado" definido pelo controller). Sem contra-proposta do
paciente — ele só aceita/recusa, não sugere um terceiro horário.

Componente novo `resources/js/components/appointments/SlotPicker.vue`
(data + busca de horários livres + seleção) — única extração de
componente desta etapa, reaproveitado por `patient-portal/appointments/
{Create,Reschedule}.vue` e `settings/appointments/Propose.vue` (staff),
justificada por reuso real imediato em três lugares, não especulativo.

**Fora desta etapa (decisão registrada, pós-MVP ou etapa futura)**:
contra-proposta do paciente, notificação automática de vaga na lista de
espera, granularidade de encaixe por serviço/profissional, RRULE genérico
de recorrência, prazo mínimo de antecedência para cancelar/reagendar pelo
portal.
