# Gestão de Clínicas

SaaS para gestão de clínicas e consultórios (estética, odontologia,
massagens, terapias e demais estabelecimentos de atendimento).

A fundação técnica (autenticação, painel administrativo, infraestrutura
Docker, qualidade de código), a **Fase 1** (organizações, entidades legais
CPF/CNPJ, unidades, endereços, horários de funcionamento e contexto ativo
multiempresa) e a página pública da clínica (landing, galeria, SEO) estão
implementadas — ver [docs/architecture/overview.md](docs/architecture/overview.md)
e [docs/modules/](docs/modules/). Profissionais, pacientes, agenda,
prontuário, financeiro, produtos, estoque e vendas ainda não foram
implementados.

## Stack

Laravel 13 · PHP 8.4 · Filament 5 · Livewire 4 · Inertia.js 3 · Vue 3 ·
TypeScript · Tailwind CSS · shadcn-vue · PostgreSQL 17 · Redis · MinIO ·
Mailpit · Docker Compose. Detalhes em
[docs/architecture/overview.md](docs/architecture/overview.md).

## Requisitos mínimos

- Docker e Docker Compose (plugin `docker compose`, v2+)
- Não é necessário PHP, Composer ou Node instalados no host — tudo roda em
  containers.

## Instalação e inicialização

```bash
git clone <url-do-repositorio> gestao-clinicas
cd gestao-clinicas
make init
```

`make init` é idempotente: cria o `.env` (com senhas locais geradas
automaticamente) apenas se ele ainda não existir, constrói as imagens, sobe
os serviços, instala dependências, roda as migrations pendentes, compila os
assets e executa um diagnóstico completo. Detalhes em
[docs/architecture/development.md](docs/architecture/development.md).

## URLs locais

| Serviço | URL |
|---|---|
| Aplicação | http://localhost:8080 |
| Galeria pública ("Ver todas") | http://localhost:8080/galeria |
| Login | http://localhost:8080/login |
| Cadastro | http://localhost:8080/register |
| Dashboard (autenticado) | http://localhost:8080/dashboard |
| Perfil (dados pessoais, foto) | http://localhost:8080/settings/profile |
| Onboarding de organização | http://localhost:8080/onboarding/organization |
| Dados da clínica | http://localhost:8080/settings/organization |
| Dados legais e fiscais | http://localhost:8080/settings/legal-entities |
| Unidades | http://localhost:8080/settings/units |
| Painel administrativo (Filament) | http://localhost:8080/admin |
| Vite (dev server) | http://localhost:5173 |
| Mailpit (e-mails locais) | http://localhost:8025 |
| MinIO Console | http://localhost:9001 |
| PostgreSQL | `127.0.0.1:5433` |

## Comandos Make

```text
make help            # lista todos os comandos com exemplos
make init             # prepara o projeto (idempotente)
make up / make down    # sobe/derruba os containers
make restart           # reinicia os containers
make ps / make logs    # status / logs
make shell              # shell no container app (não-root)
make root-shell         # shell no container app (root)
make artisan cmd="..."  # php artisan ...
make composer cmd="..." # composer ...
make npm cmd="..."      # npm ... (container node)
make migrate            # migrations pendentes
make seed               # roda os seeders
make fresh               # migrate:fresh — pede confirmação
make test                # testes de backend + frontend
make test-backend        # apenas Pest
make test-frontend       # apenas Vitest
make lint                # Pint --test + ESLint
make format               # aplica Pint + Prettier
make analyse              # Larastan/PHPStan + vue-tsc
make build-assets         # vite build
make doctor                # diagnóstico completo da infraestrutura
make db / make redis       # psql / redis-cli autenticados
make clean                  # remove containers (confirma antes de apagar volumes)
```

## Executando migrations

```bash
make migrate
```

Nunca use `migrate:fresh` fora de `make fresh` (que pede confirmação
explícita) — ele apaga todas as tabelas.

## Executando os testes

```bash
make test              # backend + frontend
make test-backend       # Pest (usa o banco gestao_clinicas_test, PostgreSQL real)
make test-frontend      # Vitest
```

Ver [docs/architecture/testing.md](docs/architecture/testing.md) para a
lista completa de testes obrigatórios desta fase.

## Como criar o administrador da plataforma

```bash
make artisan cmd="app:create-platform-admin"
```

Comando interativo: solicita nome, e-mail e senha (nunca ecoada nem
registrada em log), valida a força da senha, marca o e-mail como
verificado e concede acesso ao painel Filament. Se o e-mail já existir,
pede confirmação explícita antes de promover o usuário.

Alternativa para desenvolvimento (opcional, nunca em produção): definir
`SEED_PLATFORM_ADMIN=true` e `PLATFORM_ADMIN_NAME`/`PLATFORM_ADMIN_EMAIL`/
`PLATFORM_ADMIN_PASSWORD` no `.env` e rodar `make seed`.

## Como acessar o Filament

Após criar um administrador da plataforma, acesse
http://localhost:8080/admin e entre com o e-mail/senha cadastrados. Apenas
usuários com `is_active=true`, `is_platform_admin=true` e e-mail verificado
conseguem entrar — usuários comuns recebem 403.

## Como abrir o Mailpit

Todos os e-mails enviados em desenvolvimento (verificação de e-mail,
redefinição de senha) são capturados pelo Mailpit, sem sair da rede local:
http://localhost:8025

## Como acessar o MinIO

Console web: http://localhost:9001 (usuário/senha em `MINIO_ROOT_USER`/
`MINIO_ROOT_PASSWORD` no seu `.env` local). O bucket privado
`clinical-files` é criado automaticamente pelo serviço `minio-init`.

## Como acessar o PostgreSQL

```bash
make db
```

Ou com um cliente externo: `127.0.0.1:5433`, banco/usuário/senha conforme
`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` no seu `.env` local.

## Como parar o projeto

```bash
make down
```

Para os containers e preserva os volumes (dados de Postgres/Redis/MinIO).

## Como apagar os volumes com segurança

```bash
make clean
```

Remove os containers e, somente após duas confirmações explícitas,
remove também os volumes (Postgres/Redis/MinIO) — ação irreversível.

## Fluxo de branches, CI e deploy

- `beta` = desenvolvimento/homologação. `main` = produção.
- Desenvolvimento: `git checkout beta && git pull origin beta`, trabalhar,
  `git push origin beta`.
- Integração: abrir Pull Request `beta → main`. O CI (Pint, Larastan, Pest,
  ESLint, Prettier, vue-tsc, Vitest) roda automaticamente e precisa passar
  para o merge ser liberado.
- Deploy: só acontece depois de um push efetivo em `main` (o próprio merge
  do PR) com o CI verde — nunca a partir de `beta`. Detalhes completos do
  pipeline, secrets necessários e configuração manual do GitHub/VPS em
  [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
- Nunca `git push origin main` diretamente como fluxo normal.

## Troubleshooting

- **Vite não carrega / erro de manifest**: confirme que o container `node`
  está em execução (`make ps`) ou rode `make build-assets`.
- **Porta já em uso**: ajuste `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`,
  `MAILPIT_UI_PORT`, `MINIO_API_PORT`, `MINIO_CONSOLE_PORT` no `.env`.
- **Permissões de arquivo divergentes do host**: ajuste `UID`/`GID` no
  `.env` para os valores de `id -u`/`id -g` do seu usuário e rode
  `make build` novamente.
- **Diagnóstico geral**: `make doctor` verifica banco, cache, fila,
  storage, mailer e permissões, item a item.

## Documentação adicional

- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — CI/CD, secrets, Ruleset e deploy em produção.
- [docs/architecture/overview.md](docs/architecture/overview.md) — visão geral e estrutura de pastas.
- [docs/architecture/docker.md](docs/architecture/docker.md) — detalhes de cada serviço Docker.
- [docs/architecture/development.md](docs/architecture/development.md) — fluxo de desenvolvimento local.
- [docs/architecture/testing.md](docs/architecture/testing.md) — estratégia e cobertura de testes.
- [docs/architecture/security-baseline.md](docs/architecture/security-baseline.md) — baseline de segurança.
- [docs/architecture/tenancy.md](docs/architecture/tenancy.md) — multiempresa, contexto ativo, integridade entre organizações.
- [docs/architecture/permissions.md](docs/architecture/permissions.md) — matriz de papéis e permissões (RBAC).
- [docs/architecture/auditing.md](docs/architecture/auditing.md) — auditoria, sanitização recursiva.
- [docs/pre-agendamento-futuro.md](docs/pre-agendamento-futuro.md) — arquitetura (não implementada) da busca de clínicas e pré-agendamento futuro.
- [docs/architecture/localization.md](docs/architecture/localization.md) — regra código em inglês/interface em português, vocabulário oficial.
- [docs/decisions/](docs/decisions/) — ADRs (decisões de arquitetura).
- [docs/modules/](docs/modules/) — documentação dos módulos de negócio (futuro).
- [CLAUDE.md](CLAUDE.md) / [AGENTS.md](AGENTS.md) — regras para agentes de IA (Claude Code, Laravel Boost).
