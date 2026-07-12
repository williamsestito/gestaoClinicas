#!/usr/bin/env bash
# Aguarda um host:porta TCP ficar disponivel. Usado pelo entrypoint dos
# servicos PHP (app/queue/scheduler) para aguardar Postgres e Redis.
#
# Uso: wait-for-service.sh <host> <porta> [nome-amigavel] [timeout-segundos]

set -euo pipefail

HOST="${1:?informe o host}"
PORT="${2:?informe a porta}"
NAME="${3:-$HOST:$PORT}"
TIMEOUT="${4:-60}"

elapsed=0
until (echo > "/dev/tcp/${HOST}/${PORT}") >/dev/null 2>&1; do
    if [ "$elapsed" -ge "$TIMEOUT" ]; then
        echo "[wait-for-service] Tempo esgotado aguardando ${NAME} (${HOST}:${PORT})" >&2
        exit 1
    fi
    echo "[wait-for-service] Aguardando ${NAME} (${HOST}:${PORT})..."
    sleep 2
    elapsed=$((elapsed + 2))
done

echo "[wait-for-service] ${NAME} disponivel."
