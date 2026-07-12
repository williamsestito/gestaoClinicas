# Desenvolvimento local

## Primeira execução

```bash
make init
```

Isso executa `docker/scripts/bootstrap.sh`, que é idempotente: cria o `.env`
(gerando senhas locais fortes) apenas se ele não existir, constrói as
imagens, sobe as dependências de infraestrutura, aguarda os health checks,
instala dependências Composer/npm, gera a `APP_KEY`, roda as migrations
pendentes (nunca `migrate:fresh`), compila os assets, sobe os demais
serviços e executa o diagnóstico (`app:doctor`) e os testes básicos.

## Comandos do dia a dia (Makefile)

| Comando | Finalidade |
|---|---|
| `make up` / `make down` | Sobe/derruba os containers |
| `make restart` | `down` + `up` |
| `make ps` / `make logs` | Status / logs dos containers |
| `make shell` | Shell no container `app` (usuário não-root) |
| `make root-shell` | Shell no container `app` como root |
| `make artisan cmd="migrate:status"` | Executa um comando Artisan |
| `make composer cmd="require foo/bar"` | Executa um comando Composer |
| `make npm cmd="run build"` | Executa um comando npm (container `node`) |
| `make migrate` | Executa migrations pendentes |
| `make seed` | Executa os seeders |
| `make fresh` | `migrate:fresh` — pede confirmação, é destrutivo |
| `make test` | Testes de backend (Pest) + frontend (Vitest) |
| `make lint` / `make format` | Verifica / aplica formatação (Pint + ESLint/Prettier) |
| `make analyse` | Larastan/PHPStan + vue-tsc |
| `make build-assets` | `vite build` |
| `make doctor` | Diagnóstico completo da infraestrutura |
| `make db` / `make redis` | `psql` / `redis-cli` autenticados |
| `make clean` | Remove containers; pede confirmação extra para apagar volumes |

## Criando um administrador da plataforma

```bash
make artisan cmd="app:create-platform-admin"
```

Solicita nome, e-mail e senha interativamente (a senha nunca é ecoada nem
registrada em log). Se o e-mail já existir, pede confirmação explícita
antes de promover o usuário a administrador da plataforma.

## Testando a fila

```bash
make artisan cmd="app:test-queue"
```

Despacha `InfrastructureSmokeJob` (puramente técnico, sem regra de negócio)
e aguarda, com timeout configurável, a confirmação de que o worker
(`queue`) processou o job.

## Vite dentro do Docker

O container `node` roda `vite` com `--host 0.0.0.0`, e `vite.config.ts`
configura `server.host`/`server.hmr.host` para funcionar corretamente a
partir do navegador no host (HMR via `localhost:5173`).

## Boost / Claude Code

Este projeto usa [Laravel Boost](https://laravel.com/docs/boost). O servidor
MCP é iniciado via `.mcp.json` (`php artisan boost:mcp`) e guidelines
específicas da versão de cada pacote instalado estão em `CLAUDE.md`. Regras
de projeto (arquitetura, segurança, convenções) estão em `CLAUDE.md`/`AGENTS.md`.
