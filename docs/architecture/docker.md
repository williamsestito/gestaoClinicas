# Docker

Configuração própria de Docker Compose (sem Laravel Sail), pensada para
desenvolvimento local.

## Serviços

| Serviço | Imagem | Porta local (127.0.0.1) | Finalidade |
|---|---|---|---|
| `app` | build própria (PHP 8.4-FPM) | — (interna, 9000) | Aplicação Laravel (PHP-FPM) |
| `nginx` | `nginx:1.27-alpine` | `8080` | Servidor web, encaminha PHP para `app:9000` |
| `node` | `node:24-bookworm-slim` | `5173` | Vite em modo desenvolvimento |
| `postgres` | `postgres:17-alpine` | `5433` | Banco de dados |
| `redis` | `redis:7-alpine` | — (interna) | Cache, sessão, fila |
| `queue` | build própria (mesma imagem do `app`) | — | Worker `queue:work redis` |
| `scheduler` | build própria (mesma imagem do `app`) | — | `schedule:work` |
| `minio` | `minio/minio` | `9000` (API), `9001` (console) | Storage compatível com S3 |
| `minio-init` | `minio/mc` | — | Cria o bucket privado `clinical-files` e encerra |
| `mailpit` | `axllent/mailpit` | `8025` (UI); SMTP `1025` interno | Captura de e-mails locais |

Todas as portas publicadas são vinculadas a `127.0.0.1` — nada fica exposto
em todas as interfaces de rede. Redis e PHP-FPM não publicam nenhuma porta
para o host.

## Usuário não-root e UID/GID

O container `app` (e, por extensão, `queue`/`scheduler`, que reutilizam a
mesma imagem) roda como usuário não-root `appuser`, criado com `UID`/`GID`
configuráveis via build args (variáveis `UID`/`GID` do `.env`), para que os
arquivos criados dentro do container pertençam ao mesmo usuário do host.

## Volumes nomeados

- `postgres_data`, `redis_data`, `minio_data` — dados persistentes.
- `vendor` — dependências Composer, isoladas do host para evitar diferenças
  de plataforma e acelerar rebuilds.
- `node_modules` — dependências npm, isoladas do host porque vários pacotes
  (`@tailwindcss/oxide-*`, `lightningcss-*`, `@rollup/rollup-*`) têm binários
  nativos específicos por plataforma (Linux no container vs. macOS/Windows
  no host).

## Banco de dados de testes

O container `postgres` cria automaticamente, na primeira inicialização
(diretório de dados vazio), um segundo banco de dados dedicado aos testes
automatizados (`DB_TEST_DATABASE`, padrão `gestao_clinicas_test`), via script
de init em `docker/postgres/create-test-database.sh`. Os testes nunca usam
SQLite — o projeto depende de recursos específicos do PostgreSQL nas
próximas fases.

## MinIO / disco S3

O disco Laravel `s3` (`config/filesystems.php`) já está configurado para
apontar para o MinIO local (`AWS_ENDPOINT=http://minio:9000`,
`AWS_USE_PATH_STYLE_ENDPOINT=true`, bucket `clinical-files`). O disco padrão
da aplicação continua sendo `local` (`FILESYSTEM_DISK=local`) nesta fase —
o disco `s3` existe e é testável (`php artisan app:doctor`), mas não é usado
por padrão. O bucket é criado automaticamente pelo serviço `minio-init` e
não possui nenhuma política de acesso anônimo.

## Health checks e dependências

Cada serviço com estado (Postgres, Redis, MinIO, `app`, Nginx) possui um
health check real. Os serviços que dependem de outros usam
`depends_on: condition: service_healthy`. Em particular, `queue` e
`scheduler` dependem de `app` estar saudável (além de Postgres/Redis),
porque a instalação das dependências Composer (volume `vendor`) acontece no
entrypoint do primeiro container a subir — essa dependência extra evita uma
corrida em que dois containers tentem rodar `composer install`
simultaneamente sobre o mesmo volume.

## O que o entrypoint faz (e não faz)

`docker/php/entrypoint.sh` é idempotente: garante `.env`/`.env.testing`,
cria apenas as pastas graváveis necessárias (`storage/framework/*`,
`storage/logs`, `bootstrap/cache`), roda `composer install` só se
`vendor/autoload.php` não existir, gera `APP_KEY` só se estiver vazia, e
aguarda Postgres/Redis ficarem prontos. **Nunca** roda migrations, seeders,
ou `migrate:fresh` automaticamente.

## Comandos úteis

```bash
docker compose config     # valida o compose.yaml
docker compose build      # constrói as imagens
docker compose up -d      # sobe todos os serviços
docker compose ps         # status dos containers
docker compose logs -f app
```

Prefira usar os alvos do `Makefile` (`make up`, `make logs`, `make shell`,
etc.) — ver [development.md](development.md).
