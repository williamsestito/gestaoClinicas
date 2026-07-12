#!/usr/bin/env bash
# Entrypoint idempotente para os servicos app/queue/scheduler.
#
# Deliberadamente enxuto: apenas prepara diretorios graváveis, aguarda as
# dependencias externas (Postgres/Redis) ficarem prontas e executa o
# comando recebido. NAO instala dependencias Composer, NAO gera APP_KEY,
# NAO gera .env.testing e NAO executa migrations/seeders - essas etapas
# sao responsabilidade explicita de `docker/scripts/bootstrap.sh` (ou de
# comandos manuais), executadas uma unica vez, nunca em paralelo por
# multiplos containers (app/queue/scheduler) ao subir.

set -euo pipefail

cd /var/www/html

log() {
    printf '[entrypoint] %s\n' "$1"
}

if [ ! -f .env ]; then
    log "ERRO: .env ausente. Rode 'make init' (docker/scripts/bootstrap.sh) antes de subir os serviços." >&2
    exit 1
fi

# Pastas graváveis pela aplicacao (nunca o repositorio inteiro).
for dir in storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache; do
    mkdir -p "$dir"
done

# Aguarda dependencias externas ficarem saudaveis.
/usr/local/bin/wait-for-service.sh "${DB_HOST:-postgres}" "${DB_PORT:-5432}" "PostgreSQL"
/usr/local/bin/wait-for-service.sh "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}" "Redis"

log "Inicializacao concluida, executando: $*"
exec "$@"
