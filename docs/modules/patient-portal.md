# Portal do paciente (autocadastro, login, dependentes)

## Guard e tabela próprios — nunca `users`/Fortify

`App\Models\PatientUser` (guard `patient`, tabela `patient_users`,
`config/auth.php`) é completamente separado de `App\Models\User` (guard
`web`, Fortify). Três motivos concretos:

- `users.email` tem `unique()` global — colidiria com o e-mail de qualquer
  paciente que também fosse (ou viesse a ser) staff.
- Fortify traz registro/2FA/passkeys pensados para staff; nenhum faz
  sentido para o paciente nesta etapa.
- `App\Support\Tenancy\TenantContext`/`EnsureActiveOrganization` assumem
  que todo usuário autenticado no guard `web` é staff em processo de
  onboarding (sem organização ainda) — um paciente cairia incorretamente
  no assistente de criação de clínica se compartilhasse o guard.

Broker de redefinição de senha também é próprio
(`patient_password_reset_tokens`), nunca compartilha tabela com o staff.
`bootstrap/app.php` define `redirectGuestsTo()` para mandar visitantes não
autenticados de `/portal/*` para `patient-portal.login`, não para o
`login` de staff — o redirect padrão do Laravel não é guard-aware.

## Uma conta, vários pacientes

`App\Models\PatientUserLink` (tabela `patient_user_links`) é uma tabela de
junção real — FK para os dois lados (`patient_user_id`, `patient_id`) —,
não um `hasManyThrough` (que exigiria a tabela intermediária ter FK só
para o pai, e o relacionado FK para a intermediária; aqui é o contrário: a
junção tem FK para os dois lados). Por isso
`PatientUser::patients()` é `belongsToMany`, não `hasManyThrough` — um erro
real cometido e corrigido durante esta etapa (o `hasManyThrough` gerava SQL
esperando uma coluna `patients.patient_user_link_id` que nunca existiu).

Papel do vínculo (`PatientUserLinkRole`): `self` (a conta é a própria
paciente) ou `dependent` (a conta gerencia outra pessoa — resolve também o
"portal do responsável", que `docs/modules/patients.md` deixou em aberto:
um responsável que não é ele mesmo paciente simplesmente nunca tem um
vínculo `self`, só `dependent`). Dois índices únicos parciais no banco:

- no máximo um vínculo `self` ativo por conta;
- no máximo uma conta ativa por paciente.

**Limitação aceita de MVP**: a segunda regra bloqueia um segundo
responsável (ex.: guarda compartilhada) ter login próprio para o mesmo
paciente — só a primeira conta a se vincular consegue. Documentado aqui de
propósito, não é esquecimento; um segundo responsável continua podendo
ser registrado como `PatientResponsible` (metadado, sem login).

## `PatientResponsible` (staff) e `PatientUserLink` (portal) não se misturam

São dois conceitos deliberadamente sem FK entre si:
`PatientResponsible` é metadado de contato/legal editável pela recepção;
`PatientUserLink` é controle de acesso de verdade. Uma FK entre os dois
criaria um caminho de escalada de privilégio (staff editando um
"responsável" ganharia login para alguém). O único ponto de contato é de
**invariante**, não de schema: ao cadastrar um dependente menor pelo
portal, `App\Actions\PatientPortal\AddDependentPatientAction` cria, na
mesma transação, tanto o vínculo quanto um `PatientResponsible` com
`is_legal_guardian=true` — a mesma regra (RN-004) que
`CreatePatientAction` já aplica no fluxo administrativo, só que disparada
por outra porta de entrada.

## RN-003 relaxada para o titular adulto — exceção estreita e deliberada

`CreatePatientAction` (fluxo administrativo) sempre exige ≥1 contato de
emergência. O autocadastro do portal **não** reaproveita essa Action
diretamente: para `self`, a regra é relaxada (o próprio login/telefone do
paciente já cumpre esse papel); para `dependent`, um
`PatientEmergencyContact` é criado automaticamente a partir do nome/
telefone de quem está se cadastrando (campo `responsible_phone` — a conta
de portal em si não guarda telefone nenhum, só existe para esse propósito
transitório de popular o contato de emergência e o responsável).

## Autorização: por que não é Policy

Toda Policy deste projeto (`PatientPolicy` etc.) tipa `App\Models\User
$user` e é resolvida pelo guard default (`web`). Não existe (nem faria
sentido criar) uma segunda Policy por model para um guard diferente — o
Laravel resolve exatamente uma Policy por classe. **Decisão deliberada:
nenhuma Policy para o portal.** Toda rota que toca um `Patient` específico
resolve o registro através do relacionamento
(`$patientUser->patients()->findOrFail($id)`), nunca por
route-model-binding direto — um ID de outra conta dá 404, nunca confirma a
existência do registro. Mesmo espírito anti-IDOR do resto do projeto, mas
sem reaproveitar `tenant.patient-membership` (`EnsurePatientMembership`),
que é uma guarda **de staff** sobre o cadastro administrativo — nomes
parecidos, propósitos completamente diferentes; não confundir os dois.

## Verificação de e-mail: existe, mas não bloqueia nada ainda

`PatientUser` implementa `MustVerifyEmail`, a notificação é disparada no
registro (`sendEmailVerificationNotification()` sobrescrito para apontar
à rota `patient-portal.verification.verify`, já que `verification.verify`
já pertence ao staff). Nenhuma rota do portal exige `verified` nesta
etapa — não existe ainda ação sensível o bastante para justificar
bloquear o primeiro acesso por isso (o booking só chega na Etapa 3.2).
Fácil de ligar depois (`verified:patient`) quando houver.

## Anti-bot e rate limiting

Honeypot (`website`) + tempo mínimo de preenchimento
(`form_rendered_at`) no formulário de cadastro — cópia do único padrão
anti-bot já existente no projeto
(`PublicAppointmentRequestController::looksAutomated()`), sem CAPTCHA.
Rate limiters próprios em `App\Providers\PatientPortalServiceProvider`
(`patient-register`, `patient-login`, `patient-password-reset`) —
deliberadamente **não** registrados em `FortifyServiceProvider`, mesmo
copiando o formato dos limiters de lá, porque o portal não usa Fortify.

## Login e senha sem Fortify

`PatientAuthenticatedSessionController` reproduz manualmente o padrão de
`FortifyServiceProvider::authenticateUsing()` (hash + bloqueio de conta
`is_active=false`) porque não existe hook equivalente para um segundo
guard — inline, uma vez só. Redefinição de senha usa o
`Illuminate\Auth\Passwords\PasswordBroker` padrão do Laravel contra o
provider `patient_users`, sem nada customizado além do broker/tabela
próprios.

## Um bug real encontrado e corrigido nesta etapa: `Auth::id()` sem guard

`App\Support\Auditing\AuditLogger::log()` usava `Auth::id()` (sem guard)
para `actor_user_id`. Em produção isso sempre resolvia o guard default
(`web`) — mas os testes de portal (`actingAs($patientUser, 'patient')`)
revelaram que o helper de teste `actingAs()` chama internamente
`Auth::shouldUse($guard)`, trocando o guard **default** do `AuthManager`
para `patient` pelo resto do teste. Como `actor_user_id` é `bigint` (FK
para `users`, staff), tentar gravar o ULID de um `PatientUser` ali
quebrava com "invalid input syntax for type bigint". Corrigido para
`Auth::guard('web')->id()` — correto tanto semanticamente (o ator de uma
auditoria é sempre staff) quanto defensivamente (não depende de qual
guard um código totalmente não relacionado deixou como "default" da
aplicação).

## Efeito colateral em todo o resto do app: Larastan passou a exigir guard explícito

Adicionar um segundo guard a `config/auth.php` faz o Larastan (extensão
`LoadsAuthModel`) unir os models de **todos** os guards sempre que
`$request->user()`/`Auth::user()` é chamado sem argumento de guard — não
há como o analisador estático saber qual é o guard "efetivo" em tempo de
execução. Isso quebrou a inferência de tipo em ~10 arquivos de staff que
já existiam (`ProfileController`, `SecurityController`,
`EnsureActiveOrganization`, `HandleInertiaRequests` etc.), todos corrigidos
trocando a chamada para `$request->user('web')`/`Auth::guard('web')->user()`
— mudança puramente de tipagem, sem qualquer diferença de comportamento em
runtime (o guard default da aplicação continua sendo `web`).

## Achados de security-review corrigidos no mesmo dia

- **High — redefinição de senha não rotacionava `remember_token`**: um
  cookie "lembrar de mim" roubado continuaria autenticando indefinidamente
  mesmo depois da vítima trocar a senha (a redefinição só trocava o hash).
  `ResetPatientPasswordAction` agora rotaciona `remember_token` junto,
  mesmo cuidado que `Laravel\Fortify\Actions\CompletePasswordReset` já tem
  para staff.
- **Medium — oráculo de enumeração de CPF de terceiros**: a mensagem
  "já existe um paciente com este documento" (autocadastro, adicionar
  dependente, editar perfil) confirmava, para qualquer conta autenticada
  (inclusive autocadastrada), se um CPF específico já pertence a outro
  paciente da clínica — dado sensível de alguém que nunca se cadastrou no
  portal. Mensagem trocada para algo genérico nos três pontos, e as rotas
  autenticadas de escrita (`patients.update`, `dependents.store`) ganharam
  rate limit (`patient-portal-write`, 20/min por conta) — não existia
  nenhum limite nelas antes.
- **Medium — dashboard quebrava (500) se a recepção excluísse um paciente
  vinculado ao portal**: `DeletePatientAction` não desfazia o vínculo, e
  `Patient::portalLink` soft-deletado deixava `$link->patient` nulo,
  travando o dashboard e prendendo a conta permanentemente (o índice único
  "uma conta por paciente" nunca liberava o slot). Corrigido em duas
  frentes: o dashboard agora filtra (`whereHas('patient')`) antes de
  acessar o relacionamento, e `DeletePatientAction` desfaz o vínculo de
  portal na mesma operação.

## Indicador no cadastro administrativo

`PatientController::edit()` (staff) ganhou `has_portal_account` (boolean
barato, via `Patient::portalLink` `hasOne`) e um badge em
`settings/patients/Edit.vue` — única mudança do lado administrativo,
puramente informativa, sem nenhuma ação atrelada.

## Fora do escopo desta etapa (decisão registrada)

Booking pelo portal (Etapa 3.2, que só existe depois desta etapa por
depender de login do paciente), mesclagem de cadastro autocadastrado com
um administrativo pré-existente (o próprio roadmap já chamava isso de
"adiada"), CAPTCHA além do honeypot/timing existente, 2FA/passkeys para
paciente, converter `AppointmentRequest` em conta de portal (3.2), remover/
desvincular dependente (só "adicionar" existe), segundo responsável com
login próprio para o mesmo paciente (limitação de índice único aceita,
ver acima).

## Etapa 3.2 — Booking real pelo portal

`App\Http\Controllers\PatientPortal\PatientAppointmentController` segue o
mesmo padrão anti-IDOR do resto do portal: nunca usa route-model-binding
direto de `Patient`, sempre resolve via
`$patientUser->patients()->findOrFail($patient)`. `availableSlots()` é a
única exceção que **não** recebe `{patient}` na URL — disponibilidade de
horário não é dado sensível de um paciente específico, mesmo racional do
endpoint equivalente do staff.

`App\Http\Requests\PatientPortal\BookAppointmentRequest` segue o mesmo
`authorize(): true` sem Policy do resto do portal — a autorização real é o
middleware `auth:patient`/`patient.active` mais o escopo do `Patient` via
relação. A escrita (`POST .../agendamentos`) usa o mesmo limiter
`patient-portal-write` (20/min por conta) já registrado em
`PatientPortalServiceProvider` para as demais rotas de escrita do portal —
nenhum limiter novo foi necessário.

`App\Actions\PatientPortal\BookAppointmentAction` cria o `Appointment`
sempre em `AppointmentStatus::Requested` (nunca `Confirmed` — só a recepção
confirma, ver `docs/modules/appointments.md`). Ver lá também o porquê de
`patient_user_id` ir no `after` da auditoria em vez do schema
(`actor_user_id` é FK só para `App\Models\User`, staff).

Dashboard do portal (`patient-portal/Dashboard.vue`) ganhou um botão
"Agendamentos" por paciente/dependente, linkando para
`patient-portal/appointments/Index.vue` (lista, sem cancelar/reagendar
nesta etapa) e de lá para `patient-portal/appointments/Create.vue`
(seleção de unidade/serviço/profissional/data, busca de horários livres via
`StaffAppointmentSlotFinder` reaproveitado sem alteração — o nome
"Staff" no finder é só histórico da Etapa 3.1, o algoritmo não tem nada
específico de staff).

## Etapa 3.3 — Cancelar/reagendar pelo portal + aceitar horário proposto

Paciente agora cancela (`App\Actions\PatientPortal\CancelPatientAppointmentAction`)
ou reagenda (`ReschedulePatientAppointmentAction`) o próprio agendamento —
mesmo formato das Actions equivalentes de staff, mas não as reaproveitando
diretamente (não há coluna para autoria de `PatientUser` nelas); autoria
vai no `after` do audit log (`cancelled_by`/`rescheduled_by:
'patient_portal'`, `patient_user_id`), mesma convenção de
`BookAppointmentAction`. **Decisão registrada**: sem prazo mínimo de
antecedência — nenhuma regra escrita existia para isso; mais simples
começar sem restrição e apertar depois se abuso for observado.

`PatientAppointmentController::findOwnAppointment()` é a primeira vez que o
portal escopa **dois** níveis: `$patientUser->patients()->findOrFail($patient)->appointments()->findOrFail($appointment)`
— um agendamento de outra conta (mesmo com ID válido) dá 404, nunca
confirma existência. As rotas de cancelar/reagendar/aceitar seguem o mesmo
formato (nunca route-model-binding direto de `Appointment`).

`AppointmentStatus::AwaitingConfirmation` (reservado desde a Etapa 3.1)
ganha seu primeiro uso: quando a recepção propõe outro horário
(`App\Actions\Organization\ProposeAlternateTimeAction`, ver
`docs/modules/appointments.md`), o paciente vê um aviso no
`patient-portal/appointments/Index.vue` com botões "Aceitar" (→
`AcceptProposedAppointmentTimeAction`, `Confirmed`) e "Recusar" (reaproveita
`CancelPatientAppointmentAction` com motivo fixo "Horário proposto
recusado" — a mesma rota de cancelamento, não um endpoint novo). Sem
contra-proposta do paciente.

Componente novo `resources/js/components/appointments/SlotPicker.vue`
(data + horários livres + seleção, com `baseUrl`/`date`/`startsAt` como
props/v-model) extraído de `patient-portal/appointments/Create.vue` e
reaproveitado por `Reschedule.vue` (portal) e `settings/appointments/
Propose.vue` (staff) — três usos reais, não especulativo.
