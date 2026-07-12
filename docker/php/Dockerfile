# syntax=docker/dockerfile:1

# Imagem PHP 8.4-FPM para a aplicacao Gestao de Clinicas.
# Usada pelos servicos: app, queue, scheduler (mesmo codigo, comandos diferentes).

FROM php:8.4-fpm-bookworm AS base

ARG UID=1000
ARG GID=1000
# Instale Xdebug apenas quando necessario (make shell WITH_XDEBUG=1, por exemplo).
ARG WITH_XDEBUG=false

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

# Bibliotecas de sistema necessarias para as extensoes PHP e ferramentas de apoio.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        ca-certificates \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libfreetype6-dev \
        libxpm-dev \
        libmagickwand-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensoes PHP exigidas pela aplicacao.
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        pcntl \
        posix \
        sockets \
        zip \
        exif \
        gd \
        opcache \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick

# Xdebug opcional, preparado como build argument (nao instalado por padrao).
RUN if [ "$WITH_XDEBUG" = "true" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

# Composer 2.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuracoes PHP e OPcache do projeto.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/98-opcache.ini

# Usuario nao root, com UID/GID configuraveis para casar com o host.
# -o permite reutilizar um UID/GID ja existente na imagem base (ex.: GID 20
# em Debian normalmente pertence ao grupo "dialout"), garantindo que o
# usuario/grupo "appuser" sempre existam com o UID/GID pedido.
RUN groupadd -o -g "${GID}" appuser \
    && useradd -o -u "${UID}" -g "${GID}" -m -s /bin/bash appuser

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/scripts/wait-for-service.sh /usr/local/bin/wait-for-service.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/wait-for-service.sh

# Pre-cria o diretorio do volume nomeado "vendor" com o dono correto: na
# primeira montagem, o Docker copia o conteudo (e as permissoes) da imagem
# para dentro do volume vazio, evitando que ele fique com dono root.
RUN mkdir -p /var/www/html/vendor \
    && chown -R appuser:appuser /var/www/html

USER appuser

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
