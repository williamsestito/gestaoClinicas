#!/usr/bin/env bash
# Bootstrap idempotente do ambiente de desenvolvimento local.
#
# Pode ser executado quantas vezes forem necessarias: nunca sobrescreve um
# .env existente, nunca roda migrate:fresh e nunca recria volumes ja
# existentes. Chamado por `make init`.
#
# Rigoroso de propósito: qualquer falha (health check, instalação de
# dependências, migrations, app:doctor, testes, build) interrompe o script
# imediatamente com código de saída diferente de zero. Não há "|| true"
# nem avisos que mascarem erro real.

set -euo pipefail

cd "$(dirname "$0")/../.."

log()  { echo -e "\n\033[1;36m==> $1\033[0m"; }
ok()   { echo "  [OK] $1"; }
fail() { echo "  [FALHA] $1" >&2; }

# Aguarda um serviço do compose ficar "healthy". Em caso de timeout, imprime
# os últimos logs do serviço e encerra com erro - nunca continua em silêncio.
wait_for_healthy() {
    local service="$1"
    local max_attempts="${2:-60}"
    local attempt=0

    echo -n "  aguardando ${service}..."
    while [ "$attempt" -lt "$max_attempts" ]; do
        status=$(docker compose ps --format '{{.Health}}' "$service" 2>/dev/null || true)
        if [ "$status" = "healthy" ]; then
            echo " saudável"
            return 0
        fi
        sleep 2
        attempt=$((attempt + 1))
    done

    echo " TIMEOUT"
    fail "${service} não ficou saudável em $((max_attempts * 2))s. Últimos logs:"
    docker compose logs "${service}" --tail=50 >&2
    exit 1
}

# 1-2. Docker e Docker Compose
log "Verificando Docker"
if ! command -v docker >/dev/null 2>&1; then
    fail "Docker não encontrado. Instale o Docker antes de continuar."
    exit 1
fi
ok "$(docker --version)"

if ! docker compose version >/dev/null 2>&1; then
    fail "Docker Compose (plugin) não encontrado."
    exit 1
fi
ok "$(docker compose version)"

# 3-4. .env
log "Verificando .env"
if [ -f .env ]; then
    ok ".env já existe — preservado sem alterações"
else
    cp .env.example .env
    ok ".env criado a partir de .env.example"

    gen_secret() { openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 32; }

    DB_PASS=$(gen_secret)
    REDIS_PASS=$(gen_secret)
    MINIO_PASS=$(gen_secret)

    if [[ "$OSTYPE" == "darwin"* ]]; then
        SED_INPLACE=(-i '')
    else
        SED_INPLACE=(-i)
    fi

    sed "${SED_INPLACE[@]}" \
        -e "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" \
        -e "s/^REDIS_PASSWORD=.*/REDIS_PASSWORD=${REDIS_PASS}/" \
        -e "s/^MINIO_ROOT_USER=.*/MINIO_ROOT_USER=minioadmin/" \
        -e "s/^MINIO_ROOT_PASSWORD=.*/MINIO_ROOT_PASSWORD=${MINIO_PASS}/" \
        -e "s/^AWS_ACCESS_KEY_ID=.*/AWS_ACCESS_KEY_ID=minioadmin/" \
        -e "s/^AWS_SECRET_ACCESS_KEY=.*/AWS_SECRET_ACCESS_KEY=${MINIO_PASS}/" \
        -e "s/^UID=.*/UID=$(id -u)/" \
        -e "s/^GID=.*/GID=$(id -g)/" \
        .env

    ok "Senhas locais geradas automaticamente (não exibidas neste log)"
fi

set -a
# shellcheck disable=SC1090,SC1091
# UID e uma variavel somente-leitura do bash (EUID/PPID tambem) - filtrada
# aqui para nao quebrar o source. O Docker Compose le UID/GID diretamente
# do .env por conta propria, entao isso nao afeta os containers.
source <(grep -Ev '^(UID|EUID|PPID)=' .env)
set +a

# 5. Build
log "Construindo imagens"
docker compose build

# 6-7. Sobe dependencias de infraestrutura e aguarda ficarem saudaveis
log "Iniciando PostgreSQL, Redis, MinIO e Mailpit"
docker compose up -d postgres redis minio mailpit

log "Aguardando health checks das dependências"
wait_for_healthy postgres
wait_for_healthy redis
wait_for_healthy minio

# 8. Dependências Composer - instalação única e explícita (fora do
# entrypoint), evitando corrida entre app/queue/scheduler no volume "vendor".
log "Instalando dependências Composer"
docker compose run --rm app composer install --no-interaction --prefer-dist --no-progress

# 8b. APP_KEY - somente se ainda estiver vazia.
if ! grep -q '^APP_KEY=base64:' .env; then
    log "Gerando APP_KEY"
    docker compose run --rm app php artisan key:generate --force
fi

# 8c. .env.testing - gerado a partir do .env já com APP_KEY (mesmas
# credenciais locais), apontando para o banco de dados de testes. Nunca
# sobrescreve um .env.testing existente.
if [ ! -f .env.testing ]; then
    log "Gerando .env.testing a partir do .env"
    DB_TEST_DATABASE_VALUE=$(grep -m1 '^DB_TEST_DATABASE=' .env | cut -d= -f2-)
    sed \
        -e "s/^APP_ENV=.*/APP_ENV=testing/" \
        -e "s/^DB_DATABASE=.*/DB_DATABASE=${DB_TEST_DATABASE_VALUE:-gestao_clinicas_test}/" \
        -e "s/^SESSION_DRIVER=.*/SESSION_DRIVER=array/" \
        -e "s/^MAIL_MAILER=.*/MAIL_MAILER=array/" \
        .env > .env.testing

    docker compose run --rm app php artisan key:generate --env=testing --force
fi

# 9. Dependências npm - instalação única e explícita.
log "Instalando dependências npm"
docker compose run --rm node npm ci

# 10. Sobe o app (vendor e APP_KEY já prontos, healthcheck deve passar)
log "Iniciando app"
docker compose up -d app
wait_for_healthy app

# 11. Migrations (nunca migrate:fresh)
log "Executando migrations pendentes"
docker compose exec -T app php artisan migrate --force

# 12. storage:link
log "Criando storage link (se necessário)"
docker compose exec -T app php artisan storage:link

# 13. Build de assets (garante que public/build exista mesmo sem node em dev)
log "Compilando assets"
docker compose run --rm node npm run build

# 14. Sobe os demais serviços
log "Iniciando nginx, node, queue e scheduler"
docker compose up -d nginx node queue scheduler
wait_for_healthy nginx

# 15. Diagnóstico - falha aqui interrompe o bootstrap (sem "|| warn").
log "Executando diagnóstico da infraestrutura"
docker compose exec -T app php artisan app:doctor

# 16. Testes básicos - falha aqui interrompe o bootstrap.
log "Executando testes básicos"
docker compose exec -T app php artisan test

# 17. URLs locais
log "Ambiente pronto"
cat <<EOF
  Aplicação:      http://localhost:${APP_PORT:-8080}
  Login:          http://localhost:${APP_PORT:-8080}/login
  Cadastro:       http://localhost:${APP_PORT:-8080}/register
  Dashboard:      http://localhost:${APP_PORT:-8080}/dashboard
  Admin:          http://localhost:${APP_PORT:-8080}/admin
  Vite:           http://localhost:${VITE_PORT:-5173}
  Mailpit:        http://localhost:${MAILPIT_UI_PORT:-8025}
  MinIO Console:  http://localhost:${MINIO_CONSOLE_PORT:-9001}
  PostgreSQL:     127.0.0.1:${FORWARD_DB_PORT:-5433}

  Para criar um administrador da plataforma:
    make artisan cmd="app:create-platform-admin"
EOF
