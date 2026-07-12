# syntax=docker/dockerfile:1

# Imagem Node.js 24 LTS para o servico "node" (Vite em modo desenvolvimento).

FROM node:24-bookworm-slim

ARG UID=1000
ARG GID=1000

# PHP 8.4 CLI (sem extensoes) e necessario aqui apenas porque o plugin
# @laravel/vite-plugin-wayfinder executa `php artisan wayfinder:generate`
# a cada boot do Vite, para gerar os helpers TypeScript de rotas/actions em
# resources/js/{actions,routes,wayfinder} (arquivos gerados, fora do Git).
# O Debian bookworm traz PHP 8.2 por padrao, por isso usamos o repositorio
# oficial da Sury para instalar exatamente a 8.4, igual ao restante do projeto.
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg \
    && curl -sSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php-sury.gpg \
    && echo "deb https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/php.list \
    && apt-get update && apt-get install -y --no-install-recommends php8.4-cli \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Usuario nao root com UID/GID configuraveis (mesmo padrao do docker/php/Dockerfile).
# -o permite reutilizar um UID/GID ja existente na imagem base.
RUN groupadd -o -g "${GID}" appuser \
    && useradd -o -u "${UID}" -g "${GID}" -m -s /bin/bash appuser \
    && mkdir -p /var/www/html/node_modules \
    && chown -R appuser:appuser /var/www/html

USER appuser

EXPOSE 5173
