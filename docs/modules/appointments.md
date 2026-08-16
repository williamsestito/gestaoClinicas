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
- Próprio: `manageStatus()` (check-in/início/conclusão/não comparecimento)
  também aceita o profissional vinculado ao agendamento (via
  `professional.user_id`) com a permissão granular `appointments.manage-own`
  — nunca o vínculo sozinho, sempre com membership ativo e permissão
  explícita. Este papel nunca pode criar/reagendar/cancelar.

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
