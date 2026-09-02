# Localização (pt-BR)

## Regra central

**Código sempre em inglês, interface sempre em português.** Classes,
métodos, variáveis, Models, migrations, colunas de banco, Enums, Actions,
Controllers, nomes de rota e testes técnicos permanecem em inglês — só o
texto apresentado ao usuário (labels, títulos, mensagens, botões, e-mails)
é traduzido. Nunca renomeie um campo de banco ou uma classe para
"traduzir" a interface: a tradução acontece na camada de apresentação.

## Configuração

`APP_LOCALE=pt_BR` e `APP_FALLBACK_LOCALE=pt_BR` (`.env` e default em
`config/app.php`). Fuso técnico continua `UTC`
(`config('app.timezone')`); apresentação usa o fuso do negócio
(`config('business.default_timezone')`) — ver
[overview.md](overview.md#regra-de-fuso-horário).

## Arquivos de tradução

- `lang/pt_BR/validation.php` — mensagens de validação padrão do Laravel
  em português, mais o array `attributes`: nomes amigáveis para todo campo
  técnico usado em Form Requests (`organization_name` → "nome da
  clínica", `postal_code` → "CEP", `opening_hours.*.opens_at` → "horário
  de abertura", etc.) — uma mensagem de erro nunca expõe o nome técnico do
  campo.
- `lang/pt_BR/auth.php`, `passwords.php`, `pagination.php` — traduções
  padrão do Laravel.
- `lang/pt_BR.json` — traduções pontuais de strings usadas via `__()` no
  backend (hoje: mensagens de flash toast do perfil/senha).
- Regras customizadas (`App\Rules\CpfCnpjRule`) já retornam a mensagem em
  português diretamente — não passam pelo `validation.php`.

Filament já traz suas próprias traduções em pt_BR para toda a UI genérica
(botões, paginação, notificações — `vendor/filament/*/resources/lang/pt_BR`).
Só os labels específicos desta aplicação (nome dos Resources, colunas,
filtros, ações customizadas) precisam de tradução explícita em cada
Resource — ver `getModelLabel()`/`getPluralModelLabel()`/
`getNavigationGroup()` e `->label(...)` nas colunas/campos/filtros.

## Vocabulário do produto

A interface nunca usa termos técnicos internos. Mapeamento oficial:

| Termo técnico | Termo na interface |
|---|---|
| Organization | Clínica (ou empresa) |
| LegalEntity | Dados legais e fiscais / Entidade legal |
| Unit | Unidade |
| OrganizationMembership | Vínculo com a clínica |
| UnitMembership | Acesso à unidade |
| Dashboard | Visão geral |
| Settings (pessoal) | Minha conta |
| Settings (clínica) | Configurações da clínica |
| Profile | Meu perfil |
| Security | Segurança |
| Appearance | Aparência |
| Passkey | Chave de acesso |
| Audit log | Registro de auditoria |

## Fonte local

O build não depende de acesso externo (`fonts.bunny.net` removido — ver
`vite.config.ts`/`resources/css/app.css`). A aplicação usa a pilha de
fontes do sistema operacional (`--font-sans: ui-sans-serif, system-ui,
sans-serif, ...`).

## Verificação automatizada

`resources/js/pages/no-leftover-english.spec.ts` varre o `<template>` de
toda página principal em busca de frases em inglês herdadas do starter kit
("Log in", "Sign up", "Delete account", ...) — falha o teste se alguma
reaparecer.
