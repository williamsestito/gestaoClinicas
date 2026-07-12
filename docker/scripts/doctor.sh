#!/usr/bin/env bash
# Diagnostico externo da infraestrutura, executado do host (fora dos
# containers). Complementa `php artisan app:doctor`, que roda por dentro.

set -uo pipefail

cd "$(dirname "$0")/../.."

APP_PORT="${APP_PORT:-8080}"
VITE_PORT="${VITE_PORT:-5173}"

failures=0

section() {
    echo ""
    echo "== $1 =="
}

pass() { echo "  [OK] $1"; }
warn() { echo "  [!!] $1"; }
fail() {
    echo "  [FALHA] $1"
    failures=$((failures + 1))
}

section "docker compose config"
if docker compose config >/dev/null; then
    pass "compose.yaml valido"
else
    fail "compose.yaml invalido"
fi

section "Status dos containers"
docker compose ps
required_services=(app nginx node postgres redis queue scheduler minio mailpit)
for service in "${required_services[@]}"; do
    state=$(docker compose ps --status running --services 2>/dev/null | grep -x "$service" || true)
    if [ -n "$state" ]; then
        pass "$service em execução"
    else
        fail "$service não está em execução"
    fi
done

section "php artisan app:doctor"
if docker compose exec -T app php artisan app:doctor; then
    pass "app:doctor executado com sucesso"
else
    fail "app:doctor reportou falhas"
fi

section "Endpoint /up"
if curl -fsS "http://127.0.0.1:${APP_PORT}/up" >/dev/null; then
    pass "/up respondeu"
else
    fail "/up não respondeu em http://127.0.0.1:${APP_PORT}"
fi

section "Página inicial"
if curl -fsS "http://127.0.0.1:${APP_PORT}/" >/dev/null; then
    pass "página inicial respondeu"
else
    fail "página inicial não respondeu em http://127.0.0.1:${APP_PORT}"
fi

section "Vite (modo desenvolvimento)"
if curl -fsS "http://127.0.0.1:${VITE_PORT}/@vite/client" >/dev/null; then
    pass "Vite dev server respondeu"
else
    warn "Vite dev server não respondeu em http://127.0.0.1:${VITE_PORT} (ok se node não estiver em execução)"
fi

echo ""
if [ "$failures" -gt 0 ]; then
    echo "Diagnóstico concluído com ${failures} falha(s)."
    exit 1
fi

echo "Diagnóstico concluído sem falhas."
