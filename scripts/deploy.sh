#!/usr/bin/env bash
#
# Deploy da aplicacao Gestao de Clinicas na VPS de producao.
#
# Chamado por .github/workflows/deploy.yml via SSH (depois que o CI passa em
# main) ou manualmente na VPS via `make prod-deploy`. Sincroniza o codigo com
# origin/main, sobe os containers de producao, roda migrations, otimiza o
# Laravel, reinicia os workers de fila e valida a saude da aplicacao - com
# rollback automatico do codigo (nunca do banco) se algo falhar.
#
# Nunca mexe em dados persistentes: nao apaga volumes (postgres_data/
# redis_data/minio_data sao volumes nomeados, nunca tocados por
# build/up/down sem "-v"), nao roda migrate:fresh/db:wipe, nao reverte
# migrations automaticamente.
#
# Toda a logica vive dentro de funcoes chamadas a partir de main(), que so e
# invocada na ultima linha do arquivo. Isso importa: como este script roda
# `git reset --hard` sobre o proprio arquivo (update_repository), o bash so
# executa o codigo depois de ter lido o arquivo inteiro para montar as
# definicoes de funcao - nao ha risco de "auto-modificacao" no meio da
# execucao.

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$SCRIPT_DIR"

COMPOSE_PROD="docker compose -f compose.yaml -f compose.prod.yaml"
APP_SERVICE="app"
NODE_SERVICE="node"

# Servicos long-running (ficam "up" para sempre). "minio-init" e
# PROPOSITALMENTE um job one-shot (compose.yaml: "restart: no") que cria/
# configura o bucket e termina - nunca entra nesta lista, e validado a
# parte em wait_for_minio_init().
LONG_RUNNING_SERVICES=(app nginx postgres redis minio node queue scheduler)

HEALTH_CHECK_URL="${HEALTH_CHECK_URL:-https://gestao.espacodudaalmeida.com.br/up}"
HEALTH_CHECK_ATTEMPTS="${HEALTH_CHECK_ATTEMPTS:-10}"
HEALTH_CHECK_DELAY="${HEALTH_CHECK_DELAY:-3}"

WAIT_SERVICES_ATTEMPTS="${WAIT_SERVICES_ATTEMPTS:-60}"
WAIT_SERVICES_DELAY="${WAIT_SERVICES_DELAY:-3}"
MINIO_INIT_ATTEMPTS="${MINIO_INIT_ATTEMPTS:-30}"
MINIO_INIT_DELAY="${MINIO_INIT_DELAY:-2}"

# So estes long-running tem healthcheck definido em compose.yaml - node,
# queue e scheduler nao tem, entao so exigimos "running" deles.
service_has_healthcheck() {
    case "$1" in
        app | nginx | postgres | redis | minio) return 0 ;;
        *) return 1 ;;
    esac
}

log() {
    printf '\n==> %s\n' "$1"
}

validate_environment() {
    log "Validando ambiente..."

    command -v docker >/dev/null 2>&1 || { echo "::error::docker nao encontrado no PATH." >&2; exit 1; }
    docker compose version >/dev/null 2>&1 || { echo "::error::plugin 'docker compose' nao encontrado." >&2; exit 1; }
    command -v git >/dev/null 2>&1 || { echo "::error::git nao encontrado no PATH." >&2; exit 1; }
    command -v curl >/dev/null 2>&1 || { echo "::error::curl nao encontrado no PATH (necessario para o health check)." >&2; exit 1; }
    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || { echo "::error::${SCRIPT_DIR} nao e um repositorio Git valido." >&2; exit 1; }

    [ -f compose.yaml ] || { echo "::error::compose.yaml nao encontrado em ${SCRIPT_DIR}." >&2; exit 1; }
    [ -f compose.prod.yaml ] || { echo "::error::compose.prod.yaml nao encontrado em ${SCRIPT_DIR}." >&2; exit 1; }

    # O .env de producao vive SOMENTE na VPS e nunca e criado/sobrescrito por
    # este script - se ele nao existe, o bootstrap inicial ainda nao rodou
    # (ver docs/DEPLOYMENT.md).
    [ -f .env ] || { echo "::error::.env de producao ausente em ${SCRIPT_DIR} - este script nunca cria esse arquivo, ver docs/DEPLOYMENT.md." >&2; exit 1; }

    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        echo "Aviso: ha alteracoes locais em arquivos rastreados que serao descartadas por 'git reset --hard' (arquivos nao rastreados, como .env/storage, nunca sao afetados)." >&2
    fi
}

backup_current_commit() {
    # Permite reexecucao manual informando PREVIOUS_COMMIT=<sha> na env -
    # so recaptura automaticamente se ainda nao foi definido.
    if [ -z "${PREVIOUS_COMMIT:-}" ]; then
        PREVIOUS_COMMIT="$(git rev-parse HEAD)"
    fi
    export PREVIOUS_COMMIT
    log "Commit atual antes do deploy: ${PREVIOUS_COMMIT}"
}

update_repository() {
    log "Atualizando repositorio para origin/main..."
    git fetch origin main
    git reset --hard origin/main
    log "Repositorio atualizado para $(git rev-parse HEAD)."
}

build_containers() {
    log "Construindo imagens de producao..."
    $COMPOSE_PROD build
}

wait_for_services() {
    # NAO usa "docker compose up --wait": esse flag exige que TODO servico
    # fique "running" (ou "healthy", se tiver healthcheck) - ele nao sabe
    # distinguir um job one-shot que e SUPOSTO terminar (minio-init) de um
    # servico long-running que morreu. Foi exatamente isso que causou uma
    # falha falsa em producao: "up --wait" viu o minio-init sair (exited,
    # mesmo com exit 0 = sucesso) e interpretou como o container nao ter
    # ficado "running", derrubando o deploy inteiro (e o rollback em
    # seguida, pela mesma razao). Por isso esperamos so os servicos
    # long-running aqui, com nossa propria checagem; o minio-init e
    # validado a parte em wait_for_minio_init(), que trata exited(0) como
    # sucesso de verdade.
    log "Aguardando os servicos long-running ficarem prontos..."

    local attempt service container_id state health not_ready
    for ((attempt = 1; attempt <= WAIT_SERVICES_ATTEMPTS; attempt++)); do
        not_ready=""

        for service in "${LONG_RUNNING_SERVICES[@]}"; do
            container_id="$($COMPOSE_PROD ps -q "$service")"
            if [ -z "$container_id" ]; then
                not_ready="${not_ready}${service}(sem container) "
                continue
            fi

            state="$(docker inspect -f '{{.State.Status}}' "$container_id" 2>/dev/null || echo unknown)"

            if service_has_healthcheck "$service"; then
                health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container_id" 2>/dev/null || echo unknown)"
                if [ "$state" != "running" ] || [ "$health" != "healthy" ]; then
                    not_ready="${not_ready}${service}(${state}/${health}) "
                fi
            else
                [ "$state" = "running" ] || not_ready="${not_ready}${service}(${state}) "
            fi
        done

        if [ -z "$not_ready" ]; then
            log "Todos os servicos long-running estao prontos."
            return 0
        fi

        echo "Aguardando: ${not_ready}(tentativa ${attempt}/${WAIT_SERVICES_ATTEMPTS})..."
        sleep "$WAIT_SERVICES_DELAY"
    done

    echo "::error::Timeout aguardando servicos ficarem prontos: ${not_ready}" >&2
    return 1
}

wait_for_minio_init() {
    # minio-init e PROPOSITALMENTE one-shot (compose.yaml: "restart: no"):
    # cria/configura o bucket e termina. exited com ExitCode 0 e SUCESSO,
    # nunca uma falha - so um ExitCode != 0 e rejeitado.
    log "Validando o job one-shot minio-init..."

    local container_id
    # "-a"/"--all" e essencial aqui: por padrao "ps -q" so lista containers
    # em execucao, e a esta altura o minio-init normalmente ja terminou -
    # sem "-a" o container nao apareceria e o exit code nunca seria checado.
    container_id="$($COMPOSE_PROD ps -a -q minio-init)"

    if [ -z "$container_id" ]; then
        echo "Aviso: container minio-init nao encontrado (pode ja ter sido removido apos concluir com sucesso)." >&2
        return 0
    fi

    local attempt state exit_code
    for ((attempt = 1; attempt <= MINIO_INIT_ATTEMPTS; attempt++)); do
        state="$(docker inspect -f '{{.State.Status}}' "$container_id" 2>/dev/null || echo unknown)"

        if [ "$state" = "exited" ]; then
            exit_code="$(docker inspect -f '{{.State.ExitCode}}' "$container_id" 2>/dev/null || echo -1)"
            if [ "$exit_code" -eq 0 ]; then
                log "minio-init concluiu com sucesso (exit 0)."
                return 0
            fi
            echo "::error::minio-init terminou com exit code ${exit_code} (esperado 0)." >&2
            return 1
        fi

        echo "Aguardando minio-init concluir (estado atual: ${state}, tentativa ${attempt}/${MINIO_INIT_ATTEMPTS})..."
        sleep "$MINIO_INIT_DELAY"
    done

    echo "::error::Timeout aguardando minio-init concluir (estado atual: ${state})." >&2
    return 1
}

start_containers() {
    log "Subindo containers de producao..."
    $COMPOSE_PROD up -d --remove-orphans

    wait_for_services
    wait_for_minio_init
}

build_frontend_assets() {
    # Explicito e deterministico: nao depende do Compose decidir recriar (ou
    # nao) o container "node" nem da logica do proprio command dele - roda
    # "npm run build" direto, sempre, garantindo que o front fique
    # atualizado neste deploy mesmo se o container node ja estava de pe.
    # Esta e a UNICA chamada de "npm run build" em producao - o command do
    # servico "node" em compose.prod.yaml deliberadamente NAO builda sozinho,
    # para nao rodar em paralelo com este exec (node nao tem healthcheck, ou
    # seja wait_for_services() so exige "running" dele, nao esperaria esse
    # build terminar antes de chegar aqui) e arriscar escrita concorrente em
    # public/build.
    log "Compilando assets do frontend..."
    $COMPOSE_PROD exec -T "$NODE_SERVICE" npm run build
}

run_migrations() {
    log "Executando migrations..."
    $COMPOSE_PROD exec -T "$APP_SERVICE" php artisan migrate --force
}

optimize_laravel() {
    log "Limpando caches antigos..."
    $COMPOSE_PROD exec -T "$APP_SERVICE" php artisan optimize:clear

    log "Gerando caches de producao (config/route/view/event)..."
    $COMPOSE_PROD exec -T "$APP_SERVICE" php artisan optimize
}

restart_workers() {
    # queue:work (nao queue:listen) mantem o codigo antigo carregado em
    # memoria durante toda a vida do processo - precisa ser avisado
    # explicitamente apos um deploy. queue:restart sinaliza (via cache) para
    # o worker atual finalizar o job em andamento e sair; o
    # "restart: unless-stopped" do compose.yaml sobe o container de novo em
    # seguida, ja lendo o codigo novo do disco. O scheduler nao precisa
    # disso: cada tarefa agendada roda em um processo `php artisan` novo.
    log "Reiniciando workers de fila..."
    $COMPOSE_PROD exec -T "$APP_SERVICE" php artisan queue:restart
}

health_check() {
    log "Verificando saude da aplicacao em ${HEALTH_CHECK_URL}..."
    local i
    for ((i = 1; i <= HEALTH_CHECK_ATTEMPTS; i++)); do
        if curl -fsS --max-time 5 "$HEALTH_CHECK_URL" >/dev/null 2>&1; then
            log "Aplicacao saudavel."
            return 0
        fi
        echo "Tentativa ${i}/${HEALTH_CHECK_ATTEMPTS} falhou, aguardando ${HEALTH_CHECK_DELAY}s..."
        sleep "$HEALTH_CHECK_DELAY"
    done

    echo "::error::Health check falhou apos ${HEALTH_CHECK_ATTEMPTS} tentativas em ${HEALTH_CHECK_URL}." >&2
    return 1
}

show_status() {
    log "Status final dos containers:"
    $COMPOSE_PROD ps
}

show_diagnostics() {
    echo "--- docker compose ps ---"
    $COMPOSE_PROD ps || true
    echo "--- logs (ultimas 100 linhas por servico - nunca imprime .env/segredos) ---"
    $COMPOSE_PROD logs --tail=100 || true
}

rollback_code() {
    if [ -z "${PREVIOUS_COMMIT:-}" ]; then
        echo "::warning::PREVIOUS_COMMIT indisponivel - rollback automatico nao e possivel. Reverta manualmente (git reset --hard <commit-anterior>) e reexecute este script." >&2
        return 1
    fi

    log "ROLLBACK: revertendo codigo para ${PREVIOUS_COMMIT}..."
    # "|| return 1" em cada etapa e proposital e nao redundante com o
    # errexit do script: esta funcao e chamada de dentro de "if
    # rollback_code; then" em on_failure, e o "if" suspende o errexit para
    # toda a execucao (inclusive nestas chamadas aninhadas) - sem o "||"
    # explicito, uma falha no meio do rollback passaria batido e a funcao
    # ainda retornaria sucesso (status do ultimo comando, o echo final).
    git reset --hard "$PREVIOUS_COMMIT" || return 1

    build_containers || return 1
    start_containers || return 1
    build_frontend_assets || return 1
    optimize_laravel || return 1
    restart_workers || return 1

    echo "::warning::Rollback de codigo concluido para ${PREVIOUS_COMMIT}. Migrations NAO foram revertidas automaticamente (risco de perda de dados) - se o deploy que falhou adicionou migrations incompativeis com o codigo anterior, revise manualmente antes de tentar o proximo deploy." >&2
}

on_failure() {
    local exit_code=$?
    trap - ERR
    echo "::error::Falha durante o deploy (exit ${exit_code})." >&2
    show_diagnostics

    if rollback_code; then
        echo "::error::Deploy falhou, mas o rollback de codigo foi concluido - producao esta rodando o commit anterior (${PREVIOUS_COMMIT})." >&2
    else
        echo "::error::Deploy falhou E o rollback automatico nao foi possivel - intervencao manual necessaria AGORA." >&2
    fi
    exit 1
}

main() {
    validate_environment
    backup_current_commit

    trap on_failure ERR

    update_repository
    build_containers
    start_containers
    build_frontend_assets
    run_migrations
    optimize_laravel
    restart_workers
    health_check

    trap - ERR
    show_status
    log "Deploy concluido com sucesso (commit $(git rev-parse HEAD))."
}

main "$@"
