# Recursos compartilhados (salas/equipamentos)

## `SharedResource`, não `Resource`

O model se chama `App\Models\SharedResource`, não `Resource` — nome
deliberadamente diferente do conceito ("recurso compartilhado") por um
motivo puramente técnico: `Resource` colide com o pseudo-tipo `resource` do
PHP/PHPDoc (usado por handles de arquivo/stream). O fixer `phpdoc_types` do
Pint normaliza qualquer variação de caixa de "resource" para minúsculo
sempre que aparece como argumento de generics em um bloco de comentário
(`@return HasMany<Resource, $this>` → `@return HasMany<resource, $this>`),
corrompendo a referência à classe real e quebrando o Larastan. A tabela no
banco continua se chamando `resources` (nome de tabela é uma string comum,
sem esse conflito) — só a classe PHP muda de nome.

## Pertence a exatamente uma unidade

Diferente de `Service`/`ProfessionalService` (que usam um enum de
`unit_scope` para "zero a muitas unidades opcionais"), `Resource` tem uma
FK obrigatória `unit_id` — pertence a exatamente uma unidade, nunca à
organização inteira. Um enum de escopo seria o padrão errado aqui; a FK
direta é o mesmo racional de `Unit belongsTo LegalEntity`.

CRUD completo segue o template de `Specialty` (entidade catálogo mais
simples e completa já implementada): soft delete + restore (sempre volta
`Inactive`, reativação é decisão separada), índice único parcial
`(organization_id, unit_id, name)` que ignora `deleted_at` (desde o
início, não como patch — diferente de Specialty, que só recebeu essa
correção depois). Permissões `resources.view`/`resources.manage`
(`ClinicAdmin` tem as duas; `UnitManager`/`Auditor` só view;
`Reception`/`Professional`/`Finance` nenhuma — mesmo padrão de
Specialty/Service).

## Vínculo com `Appointment`: direto, não via `Service`

Um agendamento pode usar 0-N recursos simultaneamente — pivô
`appointment_resource` (chave primária composta `(appointment_id,
resource_id)`, sem `id` próprio: diferente de `professional_specialty`/
`service_specialty` (modelos reais, criados via `Model::create()`), este
pivô é gravado via `belongsToMany()->sync()/attach()`, que não gera ULID
para uma coluna `id` fora do fluxo normal de criação — chave composta é o
padrão correto aqui). Chaves de pivô explícitas nos dois lados
(`Appointment::resources()`/`SharedResource::appointments()`) porque o
padrão do Eloquent as inferiria a partir do nome da classe
(`shared_resource_id`), não da coluna real (`resource_id`).

Decisão de design: vínculo direto com `Appointment`, não com `Service`
("este serviço sempre precisa desta sala" seria uma segunda camada de
configuração sem necessidade clara agora — fica para uma etapa futura se
surgir).

## Conflito por recurso — nunca dispensado pelo "encaixe"

`App\Support\Availability\ResourceOverlapGuard::assertNoConflict()` —
mesmo formato de `AppointmentOverlapGuard` (advisory lock
`pg_advisory_xact_lock(crc32($resource->id))`, mesmos estados bloqueantes),
mas por `resource_id` em vez de `professional_id`. Diferente do conflito de
profissional, este **nunca** é dispensado pelo toggle de "encaixe"
(`Organization.allow_appointment_overlap`, ver
`docs/modules/appointments.md`) — duas pessoas não podem usar a mesma sala
fisicamente ao mesmo tempo, mesmo com encaixe habilitado; só a agenda
humana do profissional admite encaixe.

`CreateAppointmentAction`/`RescheduleAppointmentAction` revalidam o
conflito de cada recurso vinculado sempre, dentro da mesma transação do
guard de profissional.
