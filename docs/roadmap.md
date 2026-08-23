# Roadmap de evolução

Documento de retomada: para onde o projeto vai a partir do estado atual e em
que ordem. Substitui a seção "Próximas fases" de
[modules/README.md](modules/README.md) como fonte única de verdade sobre
progresso — atualizar aqui a cada etapa fechada, não lá.

Cada etapa é dimensionada para caber em uma a três sessões de trabalho
focadas. Não abrir a etapa seguinte antes de fechar a checklist da atual.

## Regra de fechamento de etapa (vale para todas)

Uma etapa só é considerada concluída quando:

1. Migrations + Models + Actions + Policies + Form Requests seguem o padrão
   já usado em `app/Actions/Organization/*` (soft delete, `organization_id`/
   `unit_id` explícitos, sem exclusão física).
2. Testes Pest cobrem: isolamento multiempresa (organização/unidade não vaza
   para outra), matriz de papéis/permissões da etapa, e as regras de negócio
   críticas listadas no PDF de visão (ex.: menor sem responsável, desconto
   acima do limite, prontuário finalizado imutável).
3. `App\Support\Auditing\AuditLogger` é chamado explicitamente em toda Action
   que o PDF marca como evento auditável (seção 19.1 do documento de visão).
4. `vendor/bin/pint --dirty --format agent` e `composer analyse` (Larastan +
   vue-tsc) rodam limpos.
5. Skill `security-review` roda sobre o diff da etapa antes de fechar —
   principal barreira contra "brechas" (autorização incorreta, dado clínico
   vazando para papel administrativo, etc.).
6. `docs/modules/<modulo>.md` é criado/atualizado e este roadmap é marcado.

Rodar apenas os testes afetados (`php artisan test --filter=...`) durante o
desenvolvimento da etapa; rodar a suíte completa relevante só ao fechar.

## Estado atual (referência rápida)

Implementado: fundação técnica, multiempresa (Organization/LegalEntity/Unit +
`TenantContext`), RBAC completo, Especialidades, Serviços, Profissionais
(cadastro, jornada, ausências, vínculos), site público + SEO, leads de
pré-agendamento (`AppointmentRequest`), módulos habilitáveis por
especialidade (`ModuleKey`/`OrganizationModule`), cadastro administrativo de
pacientes (`Patient`/`PatientResponsible`/`PatientEmergencyContact`), agenda
real com conflito de verdade (`Appointment`), autocadastro/login/portal do
paciente (`PatientUser`/`PatientUserLink`, guard próprio, dependentes),
booking real pelo portal (com cancelar/reagendar), conversão assistida de
lead e de lista de espera em agendamento real, recursos compartilhados
(salas/equipamentos, com conflito próprio), "encaixe" configurável por
organização, pacotes de sessões, recorrência semanal,
`AwaitingConfirmation` (staff propõe horário, paciente aceita/recusa) e
dashboard de autoatendimento do profissional (pré-agendamentos pendentes,
"meus pacientes", lembretes tipo post-it), CPF obrigatório e popup de
confirmação no formulário público, pré-agendamentos (leads) visíveis e
canceláveis no portal do paciente, foto de perfil pelo portal, e bloqueio
de solicitação duplicada por profissional.

Não existe: Prontuário, Produto/Venda/Financeiro/Estoque, mesclagem de
pacientes, assinatura/billing, recursos vinculados por serviço, RRULE
genérico de recorrência, notificação automática de vaga na lista de
espera.

---

## Etapa 1 — Módulos por especialidade (fundação transversal) ✅ concluída em 2026-08-15

**Por que primeiro:** Prontuário (Etapa 4) precisa de formulários distintos
por especialidade (odontológico, estética, médico, centro de beleza).
Construir o encaixe agora evita retrabalho quando essas telas existirem.

Entregue: `App\Enums\ModuleKey`, tabela/model `organization_modules`,
`Organization::hasModule()`, `Enable/DisableOrganizationModuleAction`,
permissões `modules.view`/`modules.manage`, `OrganizationModulePolicy`,
tela `settings/modules` (proprietário habilita/desabilita Dental,
Aesthetics, Medical, Beauty — Core é implícito). Ver
[docs/modules/organization-modules.md](modules/organization-modules.md).
Testes:
`tests/Feature/OrganizationModules/OrganizationModuleManagementTest.php`
(10 casos, isolamento multiempresa e permissão granular cobertos).
Security-review do diff: sem achados de alta confiança.

- Enum `ModuleKey` (`core`, `dental`, `aesthetics`, `medical`, `beauty`).
- Tabela `organization_modules` (organization_id, module_key, enabled_at,
  disabled_at) — múltiplos módulos ativos simultaneamente por organização.
- `Organization::hasModule(ModuleKey $key): bool` + gate em Policy/middleware.
- Tela em `settings/` (ou recurso Filament) para o próprio proprietário
  habilitar/desabilitar módulos — mantém "autogerenciamento" do princípio 1.5
  do PDF.
- **Não** inclui ainda nenhum formulário clínico específico — só o
  interruptor e o mecanismo de leitura (`hasModule`).

## Etapa 2.1 — Pacientes: cadastro administrativo ✅ concluída em 2026-08-15

Entregue: `Patient` (nome, nome de preferência, CPF opcional, data de
nascimento obrigatória, contato, endereço opcional em bloco, foto opcional,
unidade/profissional de preferência), `PatientResponsible` (contato simples
— nome/CPF opcional/telefone/relação + flags legal/financeiro/representante,
**não** é outro `Patient`, decisão registrada em
[docs/modules/patients.md](modules/patients.md)), `PatientEmergencyContact`
(RN-003, ao menos um ativo por paciente). RN-004 (menor de 18 exige
responsável legal) reforçada em `App\Support\Patients\MinorGuardianGuard`,
tanto na criação (contra o payload) quanto na atualização (contra os
registros já persistidos). Busca de duplicidade por igualdade exata
(documento/telefone/e-mail/nome+nascimento) — só aviso, nunca bloqueio.
Permissões `patients.view`/`patients.manage`/`patients.view-own`.
Foto do paciente em disco privado (`local`), servida só por
`PatientController::showPhoto()` atrás de `PatientPolicy::view()` — nunca
por link público direto (diferente da foto do profissional, propositalmente
pública). Testes: `tests/Feature/Patients/*` (39 casos, incluindo
`PatientPhotoManagementTest` confirmando que o arquivo nunca vai para o
disco `public` e que o endpoint bloqueia acesso de fora da organização).
Security-review do diff original apontou um achado Medium (foto em disco
público) — corrigido no mesmo dia, ver
[docs/modules/patients.md](modules/patients.md#foto-do-paciente--armazenamento-privado).

## Etapa 2.2 — Pacientes: autocadastro, portal e dependentes ✅ concluída em 2026-08-15

Entregue: autocadastro público (RF-AUT-002) e login do paciente/responsável
em guard próprio (`patient`, tabela `patient_users`), completamente
separado do guard `web`/Fortify de staff — evita colidir com o e-mail
único de `users` e evita herdar comportamento pensado para staff. Uma
conta pode gerenciar vários pacientes (titular e/ou dependentes) via
`App\Models\PatientUserLink`, tabela de junção real com dois índices
únicos parciais (um vínculo `self` por conta; uma conta por paciente —
resolve também o "portal do responsável" que a Etapa 2.1 tinha deixado em
aberto). Nenhuma Policy é usada no portal — decisão deliberada, documentada
em [docs/modules/patient-portal.md](modules/patient-portal.md), já que
Policies deste projeto resolvem por classe de model contra o guard
default; toda rota escopa o `Patient` através do relacionamento
(`$patientUser->patients()->findOrFail()`), 404 em vez de vazar existência
de registro de outra conta. RN-003 relaxada para o titular adulto
autocadastrado (sem contato de emergência forçado); RN-004 continua valendo
para dependentes menores (responsável legal auto-criado). Rate limiting e
honeypot/tempo-mínimo próprios (mesmo padrão já usado no formulário
público de agendamento). Um bug real de guard-fallout foi corrigido:
`AuditLogger::log()` usava `Auth::id()` sem guard, o que testes do portal
expuseram como incorreto (`actingAs()` troca o guard default do
`AuthManager`) — corrigido para `Auth::guard('web')->id()`. Adicionar o
segundo guard também quebrou a inferência de tipo do Larastan em ~10
arquivos de staff pré-existentes (correção mecânica, sem mudança de
comportamento — guard `web` explicitado onde antes era implícito). Testes:
`tests/Feature/PatientPortal/*` (25 casos). Security-review do diff:
ver [docs/modules/patient-portal.md](modules/patient-portal.md).

**Fora desta etapa (decisão registrada):** mesclagem de cadastros
duplicados preservando histórico — continua adiada porque ainda não
existem agenda/prontuário/financeiro para reatribuir; construir junto com
essas fases evita retrabalho. Booking pelo portal (Etapa 3.2, agora
desbloqueada por esta etapa), remover/desvincular dependente, segundo
responsável com login próprio para o mesmo paciente, 2FA/passkeys para
paciente, CAPTCHA além do honeypot/timing já existente.

## Etapa 3.1 — Agenda real: núcleo ✅ concluída em 2026-08-15

Entregue: `Appointment` (status completo do PDF — seção 8.4 — ainda que só
`Confirmed` em diante seja alcançável nesta etapa, já que só staff cria),
conflito real via `App\Support\Availability\AppointmentOverlapGuard`
(contra jornada/bloqueios, reaproveitando `ProfessionalAvailabilityResolver`
sem alteração, e contra outros agendamentos do mesmo profissional, com
advisory lock do Postgres), sugestão de horário via
`App\Services\Availability\StaffAppointmentSlotFinder`, ciclo completo
(check-in/início/conclusão/não comparecimento), reagendamento e
cancelamento com motivo (histórico via `AuditLogger`, sem auto-referência
de model). Permissões `appointments.view`/`manage`/`view-own`/`manage-own`.
Ver [docs/modules/appointments.md](modules/appointments.md) — inclui o
relato de um bug real de timezone encontrado e corrigido pelos próprios
testes, e o achado de security-review (race condition no guard de
conflito) corrigido no mesmo dia. Testes:
`tests/Feature/Appointments/*` (54 casos).

**Fora desta etapa (3.2, decisão registrada):** recursos compartilhados
(salas/equipamentos — nenhuma base no código, domínio novo inteiro),
booking público (depende da Etapa 2.2, login do paciente), conversão de
`AppointmentRequest` em `Appointment` com um clique, pacotes de sessões,
recorrência, lista de espera, "encaixe" configurável (sobreposição é
sempre bloqueada nesta etapa).

## Etapa 3.2 — Booking público pelo portal + conversão de lead ✅ concluída em 2026-08-16

Recorte deliberado do bloco original da Etapa 3.2 (ver acima) — só os dois
itens já desbloqueados pelas etapas anteriores; o restante vira Etapa 3.3
(logo abaixo). Entregue:

- **Booking público real**: o paciente/dependente logado no portal
  (`App\Http\Controllers\PatientPortal\PatientAppointmentController`) cria
  um `Appointment` de verdade — reaproveita sem alteração
  `App\Support\Availability\AppointmentOverlapGuard` (staff) e
  `App\Services\Availability\StaffAppointmentSlotFinder`. Entra em
  `AppointmentStatus::Requested` (primeiro consumidor desse status, já
  reservado desde a Etapa 3.1); a recepção confirma manualmente via novo
  `App\Actions\Organization\ConfirmAppointmentAction`
  (`appointments.manage`, mesma policy de criar/reagendar/cancelar) ou
  recusa reaproveitando `CancelAppointmentAction` sem alteração.
- **Conversão de lead**: botão "Converter em agendamento" em
  `settings/site/appointment-requests/Index.vue` pré-preenche o formulário
  de criação de agendamento do staff a partir de um `AppointmentRequest`
  (fluxo assistido, não automático — o lead não tem os dados operacionais
  necessários para mapeamento 1:1). `CreateAppointmentAction` ganhou
  parâmetro opcional para marcar o lead como `Scheduled` e vinculá-lo
  (`appointment_id`, nova coluna) na mesma transação — idempotente, nunca
  converte o mesmo lead duas vezes.

Ver [docs/modules/appointments.md](modules/appointments.md) e
[docs/modules/patient-portal.md](modules/patient-portal.md) para o
detalhamento técnico completo. Testes:
`tests/Feature/PatientPortal/PatientAppointmentBookingTest.php`,
`tests/Feature/Appointments/AppointmentConfirmationTest.php`,
`tests/Feature/Appointments/ConvertAppointmentRequestTest.php` (14 casos no
total). Security-review do diff: sem achados de alta confiança — atenção
dada a IDOR (paciente só agenda para paciente vinculado à própria conta),
conflito de horário (guard reaproveitado sem alteração) e isolamento
multiempresa na conversão de lead.

**Fora desta etapa (decisão registrada, vira Etapa 3.3 ou pós-MVP)**:
recursos compartilhados (salas/equipamentos), pacotes de sessões,
recorrência, lista de espera, "encaixe" configurável, uso de
`AwaitingConfirmation` (segundo estado intermediário — só `Requested` é
usado), paciente cancelar/reagendar pelo portal, notificação automática ao
paciente quando confirmado/recusado, criação automática de `Patient` a
partir dos dados do lead.

## Etapa 3.3 — Recursos compartilhados e agenda avançada ✅ concluída em 2026-08-16

Escopo completo (o usuário optou por manter tudo junto, em vez do recorte
oferecido). Quatro dos seis itens (pacotes, recorrência, lista de espera,
encaixe) não tinham **nenhuma** regra escrita em `docs/` nem no PDF de
visão presente no repo — só uma linha neste roadmap. Regras MVP foram
inventadas e documentadas explicitamente (mesmo padrão "decisão
registrada" já usado em toda etapa anterior), para revisão futura se
divergirem do que o PDF de visão realmente pede:

- **Recursos compartilhados**: `App\Models\SharedResource` (não
  `Resource` — colide com o pseudo-tipo `resource` do PHPDoc, corrompendo
  generics via Pint), CRUD completo (template de `Specialty`), pertence a
  exatamente uma unidade (FK direta, não enum de escopo). Vínculo
  many-to-many direto com `Appointment` (pivô `appointment_resource`, sem
  passar por `Service`). Conflito próprio
  (`App\Support\Availability\ResourceOverlapGuard`) nunca dispensado pelo
  encaixe abaixo.
- **"Encaixe" configurável**: toggle `Organization.allow_appointment_overlap`,
  tudo-ou-nada por organização — relaxa só conflito de profissional,
  audita `AuditAction::ConflictDetected` quando usado. Decisão MVP:
  granularidade por serviço/profissional fica para depois.
- **Pacotes de sessões**: `App\Models\SessionPackage`, só contagem (sem
  preço/pagamento — Comercial/Financeiro não existem ainda), "restantes"
  sempre calculado a partir de agendamentos `Completed`, nunca persistido.
- **Recorrência**: só semanal, sem RRULE genérico — N `Appointment`
  independentes criados de uma vez (`recurrence_group_id` só agrupa
  visualmente), limite de 52, ocorrência conflitante é pulada sem abortar
  a série.
- **Lista de espera**: `App\Models\WaitlistEntry`, conversão manual (mesmo
  padrão de conversão assistida da 3.2 para lead), sem notificação
  automática.
- **Portal**: paciente cancela/reagenda o próprio agendamento (sem prazo
  mínimo de antecedência — decisão MVP); `AwaitingConfirmation` ganha seu
  primeiro uso real (staff propõe outro horário, paciente aceita/recusa).

Ver [docs/modules/resources.md](modules/resources.md) (novo),
[docs/modules/appointments.md](modules/appointments.md) e
[docs/modules/patient-portal.md](modules/patient-portal.md) para o
detalhamento técnico completo de cada decisão. Testes: 7 arquivos novos
(`ResourceManagementTest` — inclui o caso de conflito de recurso —,
`AppointmentOverlapConfigurationTest`, `SessionPackageManagementTest`,
`RecurringAppointmentSeriesTest`, `WaitlistConversionTest`,
`PatientAppointmentCancelRescheduleTest`, `AwaitingConfirmationFlowTest`),
mais de 60 casos no total. `vendor/bin/pint`/`composer analyse` limpos;
suíte completa (913 casos, excluídas as 2 falhas pré-existentes e não
relacionadas de `PublicAvailabilityEndpointsTest`) verde.

**Fora desta etapa (decisão registrada, pós-MVP ou etapa futura)**:
contra-proposta do paciente ao horário proposto, notificação automática de
vaga na lista de espera, granularidade de encaixe por serviço/profissional,
RRULE genérico de recorrência, prazo mínimo de antecedência para
cancelar/reagendar pelo portal, vínculo de recurso por serviço (em vez de
só por agendamento).

## Etapa 3.4 — Dashboard do profissional (autoatendimento) ✅ concluída em 2026-08-22

Entregue: `DashboardController::index()` passa a renderizar
`resources/js/components/dashboard/ProfessionalDashboard.vue` em vez do
dashboard administrativo quando o papel ativo do usuário é exatamente
`SystemRole::Professional` — alerta de pré-agendamentos pendentes,
contadores "em aberto"/"agendados"/"executados" por período (dia/semana/
mês), prévia de agenda e lembretes tipo post-it
(`App\Models\ProfessionalDashboardReminder`, único registro do módulo de
profissionais com exclusão física e sem `AuditLogger` — conteúdo pessoal,
não dado de negócio). "Meus pacientes" (`MyPatientsController`, escopado a
`primary_professional_id`) e "Meus pré-agendamentos"
(`MyAppointmentRequestsController`, autoatendimento sem `Policy` dedicada,
mesmo padrão de `MyScheduleController`) dão ao profissional acesso de
leitura/gestão só ao próprio recorte de dados. `ProfessionalPolicy`
ganhou uma exceção deliberada: um profissional autoatendido só vê a
própria ficha, nunca a de um colega, sem uma permissão dedicada para isso.

Habilitado por duas mudanças de suporte:

- `AppointmentRequest` ganhou `professional_id` (localizar depois "para
  qual profissional" era a solicitação) e correspondência automática com
  `Patient` já cadastrado (`CreateAppointmentRequestAction::resolvePatientId()`
  — por `PatientUserLink` quando logado no portal, senão por CPF → telefone
  → e-mail, nessa ordem, só como indício, nunca cria/altera um `Patient`).
  Ver [docs/modules/public-integration.md](modules/public-integration.md).
- `SeedSystemRolesAction` deixou de pular papéis já existentes: agora
  sincroniza aditivamente (`syncWithoutDetaching`) qualquer `PermissionKey`
  nova que passou a fazer parte do conjunto padrão de um papel de sistema
  desde a última sincronização daquela organização — sem isso, organizações
  criadas antes de `patients.view-own`/`appointments.view-own` existirem
  nunca receberiam essas permissões.

**Achados de security-review (corrigidos no mesmo dia, antes do commit)**:

1. `StoreAppointmentRequestRequest::professional_id` validava só
   `status=active`, sem escopo de organização — um profissional de
   qualquer organização era aceito no formulário público e gravado junto
   com o `organization_id` da instalação atual (a instalação é
   single-tenant por padrão, mas nada no banco impede uma segunda
   `Organization` de existir via autoatendimento de onboarding).
   Corrigido: validação escopada por `Organization::query()->first()->id`
   + FK composta `appointment_requests_org_professional_fk`
   (`organization_id` + `professional_id` → `professionals`), mesmo padrão
   já usado em `professional_dashboard_reminders`.
2. `UpdateOwnAppointmentRequestStatusRequest`/`NotesRequest` autorizavam só
   pelo vínculo com o profissional, sem checar `organization_id` — um
   usuário pode legitimamente ter `Professional` ativo em mais de uma
   organização, então o vínculo sozinho nunca foi suficiente (diferente do
   equivalente administrativo, `AppointmentRequestController`, que já
   checava). Corrigido com a mesma checagem explícita de organização.

Testes: `tests/Feature/Professionals/ProfessionalDashboardTest.php`,
`DashboardReminderTest.php`, `MyAppointmentRequestsTest.php`,
`tests/Feature/Patients/MyPatientsTest.php`,
`tests/Feature/Organization/SeedSystemRolesActionTest.php`, mais os casos
novos de isolamento cross-tenant em `AppointmentRequestSubmissionTest.php`
e `MyAppointmentRequestsTest.php` cobrindo os dois achados acima. Suíte
completa (965 casos, mesmas 2 falhas pré-existentes e não relacionadas de
`PublicAvailabilityEndpointsTest` já registradas na Etapa 3.3) e Vitest
(609 casos) verdes; `pint`/`composer analyse`/`vue-tsc`/ESLint/Prettier
limpos.

## Etapa 3.5 — Ajustes de QA manual pós-3.4 ✅ concluída em 2026-08-22

Achados de uma sessão de teste manual real no navegador (não coberto por
nenhum teste automatizado até então), todos corrigidos no mesmo dia:

- **CPF obrigatório no formulário público de agendamento** — decisão de
  negócio revertendo o campo `document` de opcional para obrigatório em
  `StoreAppointmentRequestRequest` (ver
  [docs/modules/public-integration.md](modules/public-integration.md)).
- **Popup de confirmação pós-envio**: `LandingSchedulingSection.vue`
  ganhou um `Dialog` (além do banner inline já existente, que podia ficar
  fora da área visível) com atalhos diretos para `/login` (login
  unificado, ver [docs/modules/patient-portal.md](modules/patient-portal.md))
  e `/portal/registrar` — o lead recém-criado não é, por si só, uma conta
  de login no portal.
- **Bug real encontrado e corrigido**: o widget de busca de
  disponibilidade (`LandingAvailabilitySearch.vue`) tinha estado próprio
  de unidade/especialidade/serviço/profissional/data que nunca era limpo
  no envio bem-sucedido do formulário — só o `useLandingScheduling()`
  compartilhado era resetado. Ganhou um `reset()` exposto via
  `defineExpose`, chamado por `LandingSchedulingSection.vue` no sucesso.
- **Rate limit de `/agendamento` aumentado de 5/min para 10/min** — o
  limite original (Fase 0.7) é por IP; 5/min se mostrou apertado até para
  reenvio legítimo (ex.: recepção/Wi-Fi compartilhado da própria clínica).
  Ver [docs/architecture/security-baseline.md](../architecture/security-baseline.md).
- **Isolamento Redis dev/teste**: `.env`/`.env.testing` apontavam para o
  mesmo banco Redis de cache — rodar a suíte completa (que não desabilita
  o throttle em todo teste de disponibilidade pública) esgotava o rate
  limit real que o navegador também usa em `localhost:8080`. Corrigido com
  `REDIS_CACHE_DB=15` isolado em `.env.testing` (não versionado).
- **Pré-agendamentos (leads) agora visíveis no portal do paciente**:
  `patient-portal/appointments/Index.vue` só mostrava `Appointment` real;
  uma `AppointmentRequest` vinculada ao paciente (`patient_id`) nunca
  aparecia em lugar nenhum do portal. Nova relação
  `Patient::appointmentRequests()` + `PatientAppointmentController::index()`
  retornando `pendingRequests` (toda solicitação com `appointment_id`
  nulo — some da lista assim que é convertida em agendamento real, nunca
  as duas ao mesmo tempo). Só leitura nesta etapa. Ver
  [docs/modules/patient-portal.md](modules/patient-portal.md).

Testes: casos novos em `AppointmentRequestSubmissionTest.php` (CPF
obrigatório), `usePublicAvailabilitySearch.spec.ts`/
`LandingAvailabilitySearch.spec.ts`/`LandingSchedulingSection.spec.ts`
(reset do widget + popup de confirmação), e
`tests/Feature/PatientPortal/PatientAppointmentRequestListingTest.php`
(5 casos, incluindo isolamento cross-patient). Suíte completa (972 casos,
mesmas 2 falhas pré-existentes de `PublicAvailabilityEndpointsTest`) e
Vitest (618 casos) verdes; `pint`/`composer analyse`/`vue-tsc`/ESLint/
Prettier limpos.

**Fora desta etapa (decisão registrada)**: cancelar/editar um
pré-agendamento pelo portal (só leitura por enquanto), notificação
automática ao paciente quando o status do pré-agendamento muda.

## Etapa 3.6 — Portal do paciente: foto, cancelar pré-agendamento e regra de duplicidade ✅ concluída em 2026-08-22

Fecha os itens deixados de fora da Etapa 3.5 e responde a um pedido
explícito de redesenho do portal:

- **Foto de perfil pelo portal**: `PatientProfileController::updatePhoto()`/
  `destroyPhoto()`/`showPhoto()` reaproveitam sem alteração
  `UpdatePatientPhotoAction`/`DestroyPatientPhotoAction` (mesmas do
  cadastro administrativo — disco `local` privado, nunca `public`), só
  trocando a autorização por Policy pelo escopo de conta do portal
  (`$patientUser->patients()->findOrFail($patient)`, mesmo padrão do
  resto do módulo). Novo `App\Http\Requests\PatientPortal\
  UpdatePatientPortalPhotoRequest`.
- **Cancelar pré-agendamento pelo portal**: `App\Actions\PatientPortal\
  CancelPatientAppointmentRequestAction` (bloqueia recancelar um já
  cancelado). `PatientAppointmentController::cancelRequest()` nunca
  resolve uma solicitação já convertida (`appointment_id` preenchido) —
  mesmo escopo de `index()`.
- **Duplicidade por profissional**: `CreateAppointmentRequestAction`
  ganhou `guardAgainstPendingDuplicateProfessional()` — quando o paciente
  já é reconhecido (CPF/telefone/e-mail ou conta logada) e já tem uma
  solicitação `Pending` com o mesmo profissional, uma nova solicitação
  para esse profissional é rejeitada (`ValidationException` em
  `professional_id`) até a anterior mudar de status ou ser cancelada.
  Decisão de negócio, não um bug: motivada por leads duplicados reais
  observados em teste manual.
- **Redesenho do portal** (pedido explícito do usuário — "tela mais
  amigável, com tom profissional"): `PatientPortalLayout.vue` (cabeçalho),
  `Dashboard.vue`/`patients/Edit.vue` (avatar com iniciais como fallback,
  Cards em vez de blocos soltos, ícones lucide, copy mais direta).
  `resources/js/lib/initials.ts` novo, compartilhado entre as duas
  páginas.

**Achado corrigido no meio da etapa**: um `php artisan wayfinder:generate`
sem `--with-form` (a Vite plugin usa `formVariants: true`, ver
vite.config.ts) regenerou `resources/js/routes`/`resources/js/actions`
sem os helpers `.form()`, quebrando `vue-tsc` em ~15 páginas não
relacionadas a esta etapa. Corrigido rodando de novo com `--with-form`.
Rodar sempre com essa flag ao regenerar manualmente.

**Bug real encontrado e corrigido**: o preenchimento automático de
endereço por CEP (3 provedores em cadeia — AwesomeAPI CEP → API CEP →
ViaCEP, já implementados desde antes desta etapa em
`App\Services\PostalCodeLookupChain`) não funcionava na edição de dados
pelo portal — a rota `cep/{postalCode}` só existia dentro do grupo
`auth`/`tenant.*` de `routes/clinic.php` (staff), inacessível pelo guard
`patient`. Movida para `routes/web.php` com `auth:web,patient` (staff ou
paciente autenticado, sem contexto de organização/unidade — é uma
consulta externa cacheada, igual para os dois lados). Ver
[docs/architecture/security-baseline.md](../architecture/security-baseline.md).

**Segundo bug real encontrado e corrigido**: a validação de CPF único do
paciente (`CreatePatientRequest`/`UpdatePatientRequest`/
`UpdatePatientPortalProfileRequest`) não excluía registros arquivados
(soft-deleted), ao contrário do índice único de verdade no banco
(`patients_unique_active_document`, já parcial desde a criação da
tabela). Um paciente arquivado com CPF X bloqueava para sempre qualquer
novo cadastro com esse CPF — inclusive o próprio dono do CPF salvando o
cadastro ativo dele sem mudar nada, exatamente o que travou o teste
manual do usuário. Corrigido nas três regras com `->whereNull('deleted_at')`.
Ver [docs/modules/patients.md](modules/patients.md).

Testes: `PatientPortalPhotoManagementTest.php` (7 casos),
`CancelPatientAppointmentRequestTest.php` (5 casos), 3 casos novos em
`AppointmentRequestSubmissionTest.php` (duplicidade por profissional),
specs novos/atualizados de `Dashboard.vue`/`patients/Edit.vue`/
`appointments/Index.vue`, 2 casos novos em `PostalCodeLookupTest.php`
(acesso cross-guard do lookup de CEP), 4 casos novos entre
`PatientManagementTest.php`/`PatientPortalProfileEditTest.php` (CPF de
paciente arquivado). Suíte completa (993 casos, mesmas 2 falhas
pré-existentes) e Vitest (628 casos) verdes; `pint`/`composer analyse`/
`vue-tsc`/ESLint/Prettier limpos.

## Etapa 3.7 — Login unificado, calendário de disponibilidade e autoatendimento de agendamento

Vários ajustes pontuais pedidos após uso manual da Etapa 3.6, na área de
autenticação, nos calendários de disponibilidade e no ciclo de vida do
agendamento pelo profissional.

- **Login unificado em `/login`**: `Fortify::authenticateUsing()`
  (`App\Providers\FortifyServiceProvider::configureActions()`) passou a
  reconhecer, pelo e-mail, se a conta é de staff (`users`) ou de paciente
  (`patient_users`) e autentica no guard certo — se for paciente, loga
  direto no guard `patient` via `HttpResponseException` (interrompe o
  pipeline do Fortify antes da resposta de falha padrão). A tela
  `/portal/login` foi **removida** (controller/rota/request), por não ter
  mais utilização. "Esqueci minha senha" ganhou o mesmo tratamento:
  `App\Http\Controllers\Auth\SendPasswordResetLinkController` (rota
  `forgot-password.send`) escolhe o broker certo (`users` ou
  `patient_users`) pelo e-mail, sempre com a mesma mensagem de sucesso
  (nunca revela se a conta existe). `bootstrap/app.php`'s
  `redirectGuestsTo()` simplificado para sempre apontar para `route('login')`,
  independente do path. Consequência aceita e documentada em
  [docs/modules/patient-portal.md](modules/patient-portal.md): uma sessão
  `web` já autenticada bloqueia (pelo middleware padrão do Laravel) uma
  segunda autenticação como paciente no mesmo navegador via `/login`; o
  caminho inverso funciona normalmente.
- **Calendários de disponibilidade**: dias já passados agora ficam
  desabilitados e visualmente diferenciados nos três calendários
  construídos nesta fase (landing, novo agendamento do portal,
  reagendamento do portal) — antes só refletiam a disponibilidade
  operacional do profissional, nunca comparavam com a data atual. Lógica
  extraída para `resources/js/lib/dates.ts` (`isPastDate()`) e
  `resources/js/composables/useAvailabilityCalendarGrid.ts` (grade
  mês → dias, reaproveitada pelos três lugares em vez de recalculada cada
  vez).
- **Dashboard do profissional**: contador de pré-agendamentos passou a
  ficar sempre visível como um 4º cartão fixo (com link direto para "Meus
  pré-agendamentos"), não só como banner condicional quando há pendências.
  Visão "Mês" ganhou um calendário com marcador por dia (contagem de
  agendamentos), clicável para filtrar a lista de detalhes só daquele dia.
- **Bug real corrigido — conversão de pré-agendamento**: em "Meus
  pré-agendamentos", o profissional podia mudar o status para "Agendado"
  direto num select — isso só gravava `AppointmentRequest.status`, nunca
  criava o `Appointment` real, então o "agendamento" nunca aparecia na
  agenda do profissional (`DashboardController::professionalDashboardData()`)
  nem no portal do paciente (`PatientAppointmentController::index()`),
  ambos lendo só da tabela `appointments`. Mesmo bug existia na tela
  administrativa equivalente. Corrigido em duas frentes:
  - `UpdateOwnAppointmentRequestStatusRequest`/`UpdateAppointmentRequestStatusRequest`
    não aceitam mais `status=scheduled` (`Rule::enum(...)->except(...)`) —
    esse status só existe de verdade quando
    `App\Actions\Organization\CreateAppointmentAction` cria o `Appointment`
    e marca os dois juntos.
  - **Autoatendimento ampliado, decisão explícita do usuário**: profissional
    (e recepção/admin, que já tinham acesso amplo via `appointments.manage`)
    passam a poder converter o próprio pré-agendamento num agendamento real,
    reagendar e cancelar os próprios atendimentos —
    `AppointmentPolicy::reschedule()`/`cancel()` ganharam o mesmo caminho
    `hasOwnAccess(..., AppointmentsManageOwn)` já usado por
    `confirm()`/`manageStatus()`; novo
    `AppointmentRequestPolicy::createFromOwnRequest()` autoriza a
    conversão só do próprio pré-agendamento (o Gate do Laravel resolve a
    policy pela classe do model passado a `can()`, por isso este método
    vive na policy de `AppointmentRequest`, não na de `Appointment`).
    `AppointmentController::store()` trava explicitamente que o
    `professional_id` enviado bate com o do pré-agendamento quando a
    autorização veio só pelo caminho próprio (nunca `appointments.manage`)
    — sem isso, um profissional autorizado só a converter o próprio lead
    poderia trocar `professional_id` no formulário e agendar na agenda de
    outro profissional. Botão "Agendar" real adicionado em "Meus
    pré-agendamentos", reaproveitando a página `settings/appointments/Create.vue`
    e `CreateAppointmentAction` já existentes, sem duplicar nada.
- **Ajuste de UX pedido após teste manual**: converter um pré-agendamento
  abria o formulário de novo agendamento pedindo Unidade e Profissional de
  novo, mesmo já sendo conhecidos (a solicitação de origem já os carrega).
  `AppointmentController::create()` agora inclui `unit_id`/`professional_id`
  no `prefill`; `Create.vue` trava esses dois campos como texto fixo
  quando presentes (mostra o nome, não deixa reescolher), deixando editável
  só quando a solicitação realmente não tem um dos dois cadastrado (ex.:
  `unit_id` nulo).
- **"Agendar" vira popup de confirmação de um clique, sem reabrir
  formulário** (segundo ajuste pedido após teste manual, insistindo que os
  dados do pré-agendamento já bastam): quando o lead veio de um horário
  específico escolhido na busca de disponibilidade da landing
  (`LandingAvailabilitySearch.vue`), a clínica já sabe unidade, serviço
  real e horário exato — antes isso só existia como texto livre dentro de
  `notes`. `AppointmentRequest` ganhou `preferred_service_id` (ULID do
  serviço operacional, nunca o `SiteService` do catálogo público) e
  `preferred_starts_at` (UTC, mesma disciplina de fuso de
  `AppointmentController::store()`); `unit_id` já existia e passou a ser
  respeitado por `PublicAppointmentRequestController::store()` (antes
  sempre gravava a matriz da organização, ignorando a unidade
  efetivamente escolhida na busca). Mesmo precedente já usado para
  `professional_id` (ver migration
  `2026_08_17_000000_add_professional_id_to_appointment_requests_table.php`:
  texto livre "é impossível de consultar"). Em "Meus pré-agendamentos",
  quando os três campos estão presentes, "Agendar" abre um `Dialog` de
  confirmação (paciente pré-casado ou busca inline via
  `PatientSearchSelect.vue`, serviço/unidade/data-hora só para leitura)
  que posta direto em `POST /settings/appointments` — mesmo
  endpoint/Action da Etapa 3.7, nenhuma rota nova, conflito de horário
  (`CreateAppointmentAction` já rejeita sobreposição) aparece no próprio
  popup. Pré-agendamentos antigos ou vindos do formulário manual sem
  horário específico continuam caindo no link para a tela de conversão
  (comportamento intacto, campos novos sempre `null` para eles).

Fora de escopo desta etapa (fica para uma etapa dedicada futura, por
pedido explícito do usuário): agenda estilo Google Calendar (dia/semana)
no dashboard do profissional, com resumo por clique, contato
WhatsApp/telefone, menu de reagendar/cancelar e destaque de paciente.

Testes: `UnifiedPasswordResetLinkTest.php` (4 casos), ajustes em
`PatientPortalAuthenticationTest.php`, `ProfessionalAppointmentSelfServiceTest.php`
(11 casos — conversão, prefill de unidade/profissional, bloqueio de
conversão de colega, bloqueio de adulteração de `professional_id`,
reagendar/cancelar próprio vs. de colega, rejeição de `status=scheduled`),
ajuste em `AppointmentLifecycleTest.php` (o profissional agora *consegue*
cancelar o próprio atendimento, comportamento antigo invertido de
propósito), `useAvailabilityCalendarGrid.spec.ts`/`dates.spec.ts`/
`settings/appointments/Create.spec.ts` novos; ampliação de
`AppointmentRequestSubmissionTest.php` (unidade/serviço real e horário
exato convertido para UTC, fallback para matriz preservado, rejeita
serviço de outra organização) e `MyAppointmentRequestsTest.php` (campos
estruturados expostos/nulos), `my-appointment-requests/Index.spec.ts`
novo (popup só aparece com os três campos completos, fallback para o
link de conversão, submete os dados certos), ajustes em
`LandingAvailabilitySearch.spec.ts`/`LandingSchedulingSection.spec.ts`.
Suíte completa (1036 casos, mesmas 2 falhas pré-existentes e
não-relacionadas — `PublicAvailabilityEndpointsTest` usa uma data fixa
`after_or_equal:today` que virou passado com o avanço do relógio real) e
Vitest (673 casos) verdes; `pint`/`composer analyse`/`vue-tsc`/ESLint/
Prettier limpos.

- **Ajustes adicionais no dashboard do profissional, pedidos após teste
  manual**:
  - Campo de data no card "Agenda" (`<input type="date">` ao lado de
    Anterior/Hoje/Próximo) — pula direto para qualquer dia/semana/mês,
    sem precisar andar um de cada vez (o backend já calcula o intervalo a
    partir de `referenceDate`, ver `DashboardController::periodRange()`).
  - Layout: "Agenda" e "Avisos e lembretes" lado a lado
    (`lg:grid-cols-3`, agenda ocupando 2/3) em vez de empilhados.
  - Post-its: grade de 2 colunas em vez de 1; clicar em um abre um popup
    com o texto completo (mesmo padrão `Dialog` do resto do sistema),
    com a ação de remover dentro do popup — a exclusão pelo X do card
    continua funcionando igual.
  - **Alarme de post-it** (ex.: "tomar remédio às 12h"): campo opcional
    `alarm_at` em `ProfessionalDashboardReminder` (UTC, convertido a
    partir do horário local do navegador só no cliente,
    `Date::toISOString()` — nunca confia em fuso do servidor para algo
    puramente pessoal). Conferido só no cliente, a cada 15s enquanto o
    dashboard estiver aberto (decisão explícita do usuário: sem
    push/Service Worker nesta fase — não dispara nada se a aba estiver
    fechada). Ao bater a hora, abre um popup de alarme que só fecha pelo
    botão "Fechar alarme" (`@escape-key-down.prevent`/
    `@pointer-down-outside.prevent`), que silencia via
    `PATCH /dashboard/lembretes/{reminder}/silenciar-alarme`
    (`DismissProfessionalDashboardReminderAlarmAction` só zera
    `alarm_at` — o post-it em si não é removido).

Testes: ampliação de `DashboardReminderTest.php` (define/limpa
`alarm_at`, silencia o próprio alarme, bloqueia silenciar o de um
colega) e `ProfessionalDashboard.spec.ts` (campo de data, grade de 2
colunas, popup de post-it, alarme disparando e sendo silenciado). Suíte
completa (1038 casos, mesmas 2 falhas pré-existentes) e Vitest (678
casos) verdes; `pint`/`composer analyse`/`vue-tsc`/ESLint/Prettier
limpos.

**Fechamento da etapa — `security-review` sobre o diff completo (autoatendimento
ampliado + popup + alarme/lembretes), dois achados corrigidos antes de
fechar**:

- **IDOR real (severidade alta)**: `AppointmentController::store()`
  autorizava a conversão do próprio pré-agendamento
  (`createFromOwnRequest`) travando só que o `professional_id` enviado
  batia com o do vínculo — nunca validava o `patient_id`. Como
  `SystemRole::Professional` só recebe `AppointmentsManageOwn`/`PatientsViewOwn`
  por padrão (nunca `appointments.manage`/`patients.manage`), um
  profissional autorizado só por esse caminho conseguia criar um
  `Appointment` `Confirmed` real vinculando **qualquer paciente da
  organização**, não só os próprios — driblando por completo o escopo de
  `PatientsViewOwn` (mesma definição de "paciente próprio" de
  `PatientPolicy::hasOwnAccess()`: `patient.primary_professional_id`
  igual ao vínculo do usuário). `unit_id`/`service_id` já ficavam
  implicitamente restritos pelo `ActiveProfessionalServiceLinkResolver`
  (só combinações com vínculo ativo) — só `patient_id` estava aberto.
  **Corrigido** com um segundo `abort_unless` logo depois do já existente
  (mesmo bloco, mesma condição de entrada): exige `patient_id` igual ao
  já vinculado no pré-agendamento de origem **ou** um paciente cujo
  `primary_professional_id` já é o do próprio profissional — cobre tanto
  o popup de um clique (paciente já casado via CPF/telefone/e-mail no
  formulário público) quanto a conversão manual de um paciente já
  atendido pelo profissional, sem abrir a porta para um paciente
  qualquer. Novos testes em
  `ProfessionalAppointmentSelfServiceTest.php` (bloqueia paciente
  "estranho", permite o já casado no pré-agendamento mesmo sem
  `primary_professional_id`).
- **Logging de depuração esquecido (severidade média)**: `\Log::debug()`
  temporário deixado em `CreateAppointmentRequest::authorize()` durante a
  investigação de um 403 pontual, gravando `$this->all()` (payload
  completo, incluindo `notes` e IDs de paciente) em todo envio de
  agendamento — violava a regra do projeto de nunca logar dado sensível
  de paciente. Removido.

## Etapa 4 — Prontuário e documentos

- `MedicalRecord` versionado: rascunho editável, finalização imutável,
  correção só por adendo (RN-007).
- Separação de acesso: só profissional autor ou usuário clínico autorizado
  cria/vê conteúdo clínico (RN-006, RN-016 — proprietário não tem acesso
  clínico automático).
- Upload/captura de arquivos (PDF/JPEG/PNG), categorias, auditoria de
  visualização/download/exportação (RN-008).
- Formulários por especialidade usam o `ModuleKey` da Etapa 1 (núcleo comum
  sempre disponível; odontograma fica para pós-MVP conforme o próprio PDF).
- Portal do paciente passa a exibir registros finalizados e liberados
  (RN-014).

## Etapa 5 — Comercial (produtos, serviços, vendas)

- `Product`, precificação em modo simplificado primeiro (custo + margem +
  desconto máximo); modo avançado é incremento posterior, não bloqueia MVP.
- `Sale`/`ItemVenda` vinculados a paciente, profissional, unidade, entidade
  legal e atendimento.
- Aprovação de desconto acima do limite com justificativa e log completo
  (RN-010, RN-011).

## Etapa 6 — Financeiro

- Cobrança, parcela, recebimento — nunca exclusão física, sempre
  cancelamento/estorno/renegociação (RN-009).
- Caixa (abertura, sangria, suprimento, fechamento, reabertura autorizada) e
  contas a pagar/receber.
- Comissões e repasses básicos.

## Etapa 7 — Estoque

- Fornecedores, compras, lotes/validade, inventário, consumo automático
  vinculado a serviço quando configurado.

## Etapa 8 — Relatórios e dashboards

- Depende de dados reais das Etapas 5-7. Filtros mínimos da seção 17 do PDF
  (período, organização, entidade legal, unidade, profissional,
  especialidade, serviço, produto, paciente, status, usuário, forma de
  pagamento).

## Etapa 9 — Assinatura, trial e cobrança da plataforma (billing)

Independente das Etapas 2-8 em termos técnicos — pode ser adiantada se
houver pressão comercial para cobrar clínicas-piloto antes do MVP completo
(ver "pontos de decisão" abaixo).

- `Plan` (limites: unidades, usuários, storage) e `Subscription`
  (organization_id, status: trialing/active/past_due/canceled/expired,
  trial_ends_at, current_period_end).
- Toda organização nasce em `trialing` por N dias configuráveis — cobre o
  caso de "acesso limitado para teste" sem precisar de mecanismo à parte.
- Middleware `EnsureSubscriptionIsActive` bloqueia rotas operacionais fora de
  `trialing`/`active`, com período de carência antes do bloqueio duro.
- Integração com gateway de assinatura recorrente com boleto/PIX (Asaas,
  Iugu, Vindi ou Pagar.me — mais aderente ao público de clínicas pequenas no
  Brasil que Stripe puro) via webhook assinado.
- Job diário reavalia trials/vencimentos e dispara notificação (reusa o
  módulo de Comunicação).

## Etapa 10 — Hardening, segurança e piloto

- Checklist completo RNF-SEG-001 a 008 revisado item a item.
- `security-review` sobre o sistema inteiro (não só o diff da última etapa).
- Teste de backup/restauração, teste de carga básico.
- Migração assistida das clínicas-piloto, treinamento, central de ajuda.

---

## Pontos de decisão de negócio (revisar antes de travar a ordem)

- **Billing (Etapa 9) pode subir de posição** se houver necessidade de
  cobrar as clínicas-piloto antes do módulo comercial/financeiro estar
  pronto — os dois sistemas de "dinheiro" são independentes (um cobra a
  clínica pela plataforma, o outro é o financeiro da própria clínica).
- **Estoque (Etapa 7) pode ser adiada** para pós-MVP se o segmento piloto
  escolhido (PDF recomenda estética/massoterapia/bem-estar) não depender de
  controle de lote para o piloto inicial.

## Economia de tokens por sessão

- Abrir cada sessão lendo **só** a seção da etapa corrente aqui + o(s)
  `docs/modules/*.md` relacionado(s) — não re-explorar o repo inteiro.
- Uma etapa = um branch, um conjunto de commits coeso; evitar reabrir
  etapas já fechadas para "melhorias" fora de escopo.
- Preferir edição direta de arquivos existentes a regenerar módulos inteiros;
  usar sub-agentes de exploração só na abertura da etapa, não durante ela.
- Não construir abstração para módulo/etapa futura antes dela chegar (ex.:
  não desenhar odontograma agora só porque o PDF menciona no pós-MVP).
