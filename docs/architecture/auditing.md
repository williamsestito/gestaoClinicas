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
  organization_context_switched, unit_context_switched);
- `before_data`/`after_data` (snapshot dos campos alterados);
- IP e user agent da requisição.

Antes de gravar, `before_data`/`after_data` passam por sanitização:

- chaves sensíveis (`password`, `token`, `secret`, ...) são removidas;
- `document` (CPF/CNPJ) é mascarado, mantendo só os 2 últimos dígitos.

## O que é registrado nesta fase

Criação/atualização de Organization, LegalEntity e Unit; mudança de status
(ativar/inativar/suspender); troca de organização/unidade ativa.

## Acesso

Somente leitura, restrito a administradores da plataforma, via Filament
(`AuditLogResource` — sem create/edit/delete).
