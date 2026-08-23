# Integração pública (Etapa 2.11)

## Dois cadastros, sempre separados

```text
Professional  ≠  SiteProfessional
Service       ≠  SiteService
```

O cadastro operacional (`Professional`/`Service`) controla a operação da
clínica: status, unidades, especialidades, jornada, ausências, preço real,
observações internas. O cadastro promocional (`SiteProfessional`/
`SiteService`) controla apresentação pública: título, texto, imagem,
destaque, ordem, publicação. Nenhuma migração automática entre os dois
existiu ou existe; o vínculo é opcional e nunca funde os cadastros.

## Por que o vínculo não é uma FK composta multiempresa

`Professional`/`Service` são multiempresa (`organization_id`, ULID). Já
`site_professionals`/`site_services` são **singleton por instalação** — sem
`organization_id`, id auto-incremento — desenhados assim desde antes desta
etapa (ADR-010: cada clínica roda sua própria instalação). Adicionar
`organization_id` às tabelas de site seria uma mudança de arquitetura fora
do escopo desta etapa e contradiria seu desenho documentado.

Por isso o vínculo (`site_professionals.professional_id`,
`site_services.service_id`, ambos ULID nullable, FK `nullOnDelete`) não
pode ser protegido por FK composta `(organization_id, x_id)` como nas
demais relações multiempresa do projeto. A proteção acontece no domínio:
`LinkSiteProfessionalToProfessionalAction`/`LinkSiteServiceToServiceAction`
revalidam que o registro operacional pertence à organização ativa
(`TenantContext::organization()`) antes de gravar o vínculo — a mesma
organização que `PublicSiteController::home()` já carrega como "a"
organização desta instalação.

## Cópia controlada — nunca sincronização automática

`CopyProfessionalPublicDataAction`/`CopyServicePublicDataAction` copiam
apenas campos de uma allowlist fixa, e apenas os campos que o
administrador selecionar explicitamente (nunca todos por padrão):

| Origem (`SiteProfessional` ←) | Campo copiado |
|---|---|
| `Professional.display_name` | `name` |
| `Professional.bio` | `bio` |

| Origem (`SiteService` ←) | Campo copiado |
|---|---|
| `Service.name` | `name` |
| `Service.description` | `description` |
| `Service.default_duration_minutes` | `duration_minutes` |
| `Service.default_price_cents` | `starting_price_cents` (exige opt-in explícito) |

Nunca copiados: documento, e-mail, telefone, número de registro completo,
observações internas, jornada, ausências, código interno. A cópia só é
permitida entre registros já vinculados e é auditada
(`AuditAction::Copied`).

## Publicação — gate automático, exclusão nunca automática

`PublicSiteController` só exibe uma ficha/serviço vinculado quando o
registro operacional existe, não está excluído (`deleted_at IS NULL`,
verificado mesmo carregando com `withTrashed()` para distinguir "sem
vínculo" de "vínculo aponta para excluído") e está ativo
(`RecordStatus::Active`), além de pertencer à mesma organização carregada
na instalação. Se o profissional/serviço operacional for inativado ou
excluído, o `SiteProfessional`/`SiteService` **nunca é apagado** — apenas
deixa de aparecer publicamente, e a tela administrativa mostra um alerta
("Este profissional está inativo e não será exibido publicamente.").
Conteúdo sem vínculo nunca é afetado por essa regra — continua funcionando
de forma totalmente independente, como sempre funcionou.

## Preço público — decisão explícita, nunca automática

`SiteService.starting_price_cents` só é preenchido quando o administrador
inclui esse campo explicitamente na ação de cópia — nunca porque o serviço
operacional tem `default_price_cents` configurado. Preço de profissional
específico não é usado no site nesta fase.

## Disponibilidade pública

Implementada (Etapa 3.2): `App\Http\Controllers\PublicAvailabilityController` +
`App\Services\Availability\PublicAvailabilityFinder` expõem unidade →
especialidade → serviço → profissional → data → horário, somente leitura,
sem autenticação (`throttle:60,1`). Nunca cria/reserva nada — só teoriza
disponibilidade a partir de jornada/bloqueios reais. A solicitação manual
de agendamento (`PublicAppointmentRequestController`) continua sendo o
único jeito de efetivamente registrar um lead.

`AppointmentRequest.professional_id` (ULID, FK para `professionals`,
adicionada depois, no mesmo espaço de id do cadastro operacional — ao
contrário de `service_id`, que referencia o catálogo promocional
`site_services`, um id numérico diferente) é preenchida quando a pessoa
escolhe um profissional específico — seja num horário concreto da busca de
disponibilidade (`slot.professional_id`, sempre um profissional real,
mesmo em modo "qualquer profissional"), seja no card de "Agendar" de um
profissional com cadastro operacional vinculado (`PublicProfessional.professional_id`,
nulo para uma ficha puramente promocional). Permite ao profissional
localizar depois a própria solicitação em "Meus pré-agendamentos" (ver
`App\Http\Controllers\Organization\MyAppointmentRequestsController`) — sem
essa coluna, o único registro de "para qual profissional" era texto livre
dentro de `notes`, impossível de consultar.

**Escopo de organização (achado de security-review, corrigido no mesmo
dia)**: a validação de `professional_id` em `StoreAppointmentRequestRequest`
e a FK no banco (`appointment_requests_org_professional_fk`, composta por
`organization_id` + `professional_id`, mesmo padrão de
`professional_dashboard_reminders`) exigem que o profissional pertença à
mesma organização resolvida por `Organization::query()->first()` — sem
isso, um profissional ativo de qualquer organização era aceito e gravado
junto com o `organization_id` da instalação atual, uma inconsistência
cross-tenant. `UpdateOwnAppointmentRequestStatusRequest`/
`UpdateOwnAppointmentRequestNotesRequest` ganharam a mesma checagem
explícita de `organization_id` (defesa em profundidade: um usuário pode
legitimamente ter `Professional` ativo em mais de uma organização, então o
vínculo com o profissional sozinho nunca foi suficiente).

### Correspondência com paciente já cadastrado

`AppointmentRequest.patient_id` (ULID, FK para `patients`, `nullOnDelete`) e
`AppointmentRequest.document` (CPF, dígitos apenas — **obrigatório**,
decisão de negócio revertida em 2026-08-22; era opcional até então) são
resolvidos por `App\Actions\Public\CreateAppointmentRequestAction` no
momento da criação — nunca depois, e nunca cria/altera um `Patient` a
partir de um lead público:

- Quem envia já logado no portal do paciente (`request()->user('patient')`)
  é vinculado diretamente ao próprio paciente da conta (vínculo `self` em
  `PatientUserLink`), sem heurística nenhuma.
- Anônimo: tenta localizar um `Patient` já cadastrado na organização, na
  ordem CPF → telefone (`phone` ou `whatsapp`) → e-mail — CPF primeiro por
  ser o identificador mais confiável. Sem correspondência por nenhum dos
  três, a solicitação segue sem paciente vinculado (`patient_id = null`),
  nunca bloqueia o envio (o CPF em si é obrigatório, mas encontrar um
  `Patient` correspondente não é).

O CPF é o único campo novo verdadeiramente sensível deste formulário
público; a validação (`App\Rules\CpfCnpjRule`) e a normalização
(`App\Support\Documents\Document::onlyDigits()`) são as mesmas usadas no
cadastro administrativo de pacientes.

### Bloqueio de duplicidade por profissional (Etapa 3.6)

Quando o paciente é reconhecido (por qualquer um dos três critérios acima,
ou por conta logada) **e** já tem uma `AppointmentRequest` `Pending` com o
mesmo `professional_id`, uma nova solicitação para esse mesmo profissional
é rejeitada (`ValidationException` em `professional_id`,
`guardAgainstPendingDuplicateProfessional()`) até a solicitação anterior
mudar de status (contatada/agendada) ou ser cancelada — pelo paciente
(portal, ver `docs/modules/patient-portal.md`) ou pela clínica. Não impede
uma solicitação para um profissional diferente, nem se `patient_id` não
foi resolvido (pessoa não reconhecida).

### Unidade/serviço reais e horário exato (Etapa 3.7)

Mesmo precedente de `professional_id` (texto livre "é impossível de
consultar" — ver acima): quando a pessoa escolhe um horário específico na
busca de disponibilidade (`resources/js/components/landing/LandingAvailabilitySearch.vue::chooseTimeForScheduling()`),
três campos estruturados adicionais são gravados, todos `null` para quem
usa só o formulário manual sem escolher um horário:

- `AppointmentRequest.unit_id` (já existia, mas até esta etapa
  `PublicAppointmentRequestController::store()` sempre gravava a matriz da
  organização, ignorando a unidade efetivamente escolhida na busca —
  corrigido para priorizar a unidade real quando enviada, com a matriz
  como fallback).
- `AppointmentRequest.preferred_service_id` (ULID do `Service` operacional
  — **nunca** o mesmo espaço de id de `service_id`, que referencia o
  catálogo público `site_services`).
- `AppointmentRequest.preferred_starts_at` (UTC — o horário local escolhido
  é convertido via `Carbon::parse($valor, $unit->timezone)->utc()` em
  `App\Actions\Public\CreateAppointmentRequestAction`, mesma disciplina de
  fuso de `AppointmentController::store()`).

Esses três campos permitem que "Meus pré-agendamentos" (ver
[professionals.md](professionals.md), seção "Autoatendimento de
agendamento") ofereça um "Agendar" de um clique em vez do formulário
completo de conversão — ver `MyAppointmentRequestsController::index()`,
que os expõe (`unit_name`/`preferred_service_name` já resolvidos, mais
`patient_name` quando o lead já foi casado com um `Patient`).

### Confirmação pós-envio

`resources/js/components/landing/LandingSchedulingSection.vue`: além do
banner inline (`form.recentlySuccessful`, que já existia), o envio bem-
sucedido abre um `Dialog` de confirmação — garante visibilidade
independente da posição de rolagem (o banner inline podia ficar fora da
área visível quando `LandingAvailabilitySearch` encolhia ao resetar,
deslocando o layout) — com atalhos diretos para `/login` (login
unificado staff/paciente, ver
[docs/modules/patient-portal.md](patient-portal.md)) e
`/portal/registrar`, já que o lead recém-criado não é, por si só, uma
conta de login no portal (ver seção acima).

## Interface administrativa

Nas telas `/settings/site/professionals` e `/settings/site/services`: select
para vincular (opções restritas a registros operacionais ativos da
organização ativa), indicação do vínculo atual e do estado operacional,
botão desvincular, botão "copiar dados públicos" com checkboxes por campo.
Rotas via Wayfinder; todas as ações usam `router.post`/`delete` com
`preserveScroll` e desabilitam o botão durante o processamento, evitando
submissão duplicada.
