#!/usr/bin/env bash
# Entrypoint idempotente para os servicos app/queue/scheduler.
#
# Responsabilidades:
#   - garantir que o .env existe (nunca sobrescreve um existente);
#   - garantir permissoes apenas das pastas graváveis pela aplicacao;
#   - instalar dependencias Composer apenas se necessario;
#   - gerar APP_KEY apenas se estiver vazia;
#   - aguardar Postgres e Redis ficarem prontos;
#   - executar o comando recebido pelo container.
#
# Nao executa migrations, seeders, nem qualquer operacao destrutiva.

set -euo pipefail

cd /var/www/html

log() {
    printf '[entrypoint] %s\n' "$1"
}

# 1. .env - nunca sobrescreve um arquivo existente.
if [ ! -f .env ] && [ -f .env.example ]; then
    log "Nenhum .env encontrado, copiando .env.example"
    cp .env.example .env
fi

# 2. Pastas graváveis pela aplicacao (nunca o repositorio inteiro).
for dir in storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache; do
    mkdir -p "$dir"
done

# 3. Dependencias Composer - somente se ainda nao instaladas.
if [ ! -f vendor/autoload.php ]; then
    log "vendor/autoload.php ausente, executando composer install"
    composer install --no-interaction --prefer-dist --no-progress
fi

# 4. APP_KEY - somente se estiver vazia.
if [ -f artisan ]; then
    if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
        log "APP_KEY ausente, gerando uma nova chave"
        php artisan key:generate --force
    fi
fi

# 4b. .env.testing - gerado a partir do .env ja com APP_KEY (mesmas
# credenciais locais), apontando para o banco de dados de testes. Nunca
# sobrescreve um existente.
if [ ! -f .env.testing ] && [ -f .env ]; then
    log "Gerando .env.testing a partir do .env"
    DB_TEST_DATABASE_VALUE=$(grep -m1 '^DB_TEST_DATABASE=' .env | cut -d= -f2-)
    sed \
        -e "s/^APP_ENV=.*/APP_ENV=testing/" \
        -e "s/^DB_DATABASE=.*/DB_DATABASE=${DB_TEST_DATABASE_VALUE:-gestao_clinicas_test}/" \
        -e "s/^SESSION_DRIVER=.*/SESSION_DRIVER=array/" \
        -e "s/^MAIL_MAILER=.*/MAIL_MAILER=array/" \
        .env > .env.testing
fi

if [ -f artisan ] && [ -f .env.testing ] && ! grep -q '^APP_KEY=base64:' .env.testing 2>/dev/null; then
    log "APP_KEY (testing) ausente, gerando uma nova chave"
    php artisan key:generate --env=testing --force
fi

# 5. Aguarda dependencias externas ficarem saudaveis.
/usr/local/bin/wait-for-service.sh "${DB_HOST:-postgres}" "${DB_PORT:-5432}" "PostgreSQL"
/usr/local/bin/wait-for-service.sh "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}" "Redis"

log "Inicializacao concluida, executando: $*"
exec "$@"
