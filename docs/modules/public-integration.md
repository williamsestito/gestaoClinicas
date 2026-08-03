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

Não implementada nesta fase. Nenhum endpoint expõe jornada, ausências,
bloqueios, intervalos ou slots — a landing continua usando apenas a
solicitação manual de agendamento já existente
(`PublicAppointmentRequestController`, inalterada).

## Interface administrativa

Nas telas `/settings/site/professionals` e `/settings/site/services`: select
para vincular (opções restritas a registros operacionais ativos da
organização ativa), indicação do vínculo atual e do estado operacional,
botão desvincular, botão "copiar dados públicos" com checkboxes por campo.
Rotas via Wayfinder; todas as ações usam `router.post`/`delete` com
`preserveScroll` e desabilitam o botão durante o processamento, evitando
submissão duplicada.
