#!/usr/bin/env bash
# Executado automaticamente pela imagem oficial do Postgres apenas na
# primeira inicializacao (diretorio de dados vazio), via
# /docker-entrypoint-initdb.d/. Cria o banco de dados dedicado aos
# testes automatizados, separado do banco de aplicacao.

set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE "${DB_TEST_DATABASE}"'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '${DB_TEST_DATABASE}')\gexec
EOSQL
