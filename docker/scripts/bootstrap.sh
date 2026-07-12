#!/usr/bin/env bash
# Bootstrap idempotente do ambiente de desenvolvimento local.
#
# Pode ser executado quantas vezes forem necessarias: nunca sobrescreve um
# .env existente, nunca roda migrate:fresh e nunca recria volumes ja
# existentes. Chamado por `make init`.

set -euo pipefail

cd "$(dirname "$0")/../.."

log()  { echo -e "\n\033[1;36m==> $1\033[0m"; }
ok()   { echo "  [OK] $1"; }
warn() { echo "  [!!] $1"; }

# 1-2. Docker e Docker Compose
log "Verificando Docker"
if ! command -v docker >/dev/null 2>&1; then
    echo "Docker não encontrado. Instale o Docker antes de continuar." >&2
    exit 1
fi
ok "$(docker --version)"

if ! docker compose version >/dev/null 2>&1; then
    echo "Docker Compose (plugin) não encontrado." >&2
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
# shellcheck disable=SC1091
source .env
set +a

# 5. Build
log "Construindo imagens"
docker compose build

# 6-7. Sobe dependencias de infraestrutura e aguarda ficarem saudaveis
log "Iniciando PostgreSQL, Redis, MinIO e Mailpit"
docker compose up -d postgres redis minio mailpit

log "Aguardando health checks"
for service in postgres redis minio; do
    echo -n "  aguardando ${service}..."
    for _ in $(seq 1 60); do
        status=$(docker compose ps --format '{{.Health}}' "$service" 2>/dev/null || true)
        if [ "$status" = "healthy" ]; then
            echo " saudável"
            break
        fi
        sleep 2
    done
done

# 8-10. Sobe app (composer install + APP_KEY via entrypoint) e minio-init
log "Iniciando app (instala dependências Composer e gera APP_KEY automaticamente)"
docker compose up -d app minio-init

echo -n "  aguardando app..."
for _ in $(seq 1 60); do
    status=$(docker compose ps --format '{{.Health}}' app 2>/dev/null || true)
    if [ "$status" = "healthy" ]; then
        echo " saudável"
        break
    fi
    sleep 2
done

# 9. Dependencias npm (via container node, idempotente)
log "Instalando dependências npm"
docker compose run --rm node sh -c '[ -d node_modules/.bin ] || npm install'

# 11. Migrations (nunca migrate:fresh)
log "Executando migrations pendentes"
docker compose exec -T app php artisan migrate --force

# 12. storage:link
log "Criando storage link (se necessário)"
docker compose exec -T app php artisan storage:link || true

# 13. Build de assets (garante que public/build exista mesmo sem node em dev)
log "Compilando assets"
docker compose run --rm node npm run build

# 14. Sobe os demais servicos
log "Iniciando nginx, node, queue e scheduler"
docker compose up -d nginx node queue scheduler

# 15. Diagnóstico
log "Executando diagnóstico da infraestrutura"
docker compose exec -T app php artisan app:doctor || warn "app:doctor reportou falhas — verifique acima"

# 16. Testes basicos
log "Executando testes básicos"
docker compose exec -T app php artisan test || warn "alguns testes falharam — verifique acima"

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
