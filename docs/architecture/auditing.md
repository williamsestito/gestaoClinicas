# Auditoria

Auditoria própria (ver [ADR-008](../decisions/ADR-008-custom-auditing.md)),
sem pacote externo.

## Tabela `audit_logs`

Somente `created_at` (sem `updated_at`) — um registro de auditoria nunca é
alterado. `App\Models\AuditLog::update()`/`delete()` lançam
`LogicException` incondicionalmente, como segunda camada de proteção além
da ausência de rotas de escrita.

## `AuditLogger`

`App\Support\Auditing\AuditLogger::log()` é chamado explicitamente dentro
das Actions (nunca via observer/evento mágico), para que cada registro
seja fácil de rastrear até o código que o gerou. Grava:

- ator (usuário autenticado), organização, unidade;
- ação (`App\Enums\AuditAction`: created, updated, activated, deactivated,
  deleted, restored, primary_legal_entity_changed, headquarters_changed,
  organization_context_switched, unit_context_switched,
  primary_professional_specialty_changed,
  primary_professional_registration_changed,
  primary_professional_unit_changed, copied, conflict_detected, linked,
  unlinked);
- `before_data`/`after_data` (snapshot dos campos alterados);
- IP e user agent da requisição.

Antes de gravar, `before_data`/`after_data` passam por sanitização
**recursiva** (percorre arrays aninhados em qualquer profundidade, não só
o primeiro nível):

- chaves sensíveis (`password`, `password_confirmation`, `token`,
  `access_token`, `refresh_token`, `secret`, `remember_token`, `api_key`,
  `recovery_codes`, `authentication_code`) são removidas;
- chaves de documento (`document`, `cpf`, `cnpj`) são mascaradas, mantendo
  só os 2 últimos dígitos.

## O que é registrado nesta fase

Criação/atualização de Organization, LegalEntity e Unit; ativação/
inativação; exclusão lógica e restauração (LegalEntity, Unit,
UnitMembership, OrganizationMembership); troca de entidade legal principal
e de unidade matriz; troca de organização/unidade ativa; desativação de
conta (`RequestAccountClosureAction`).

## Acesso

Somente leitura, restrito a administradores da plataforma, via Filament
(`AuditLogResource` — sem create/edit/delete).
