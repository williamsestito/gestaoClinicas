# ADR-005: Docker Compose explícito (sem Laravel Sail)

## Status

Aceito.

## Contexto

O projeto precisa de um ambiente de desenvolvimento local reprodutível com
múltiplos serviços (PHP, Nginx, Node, PostgreSQL, Redis, MinIO, Mailpit,
worker de fila, scheduler). Laravel Sail oferece isso "pronto", mas
abstrai decisões (usuário do container, extensões PHP, health checks,
volumes) que este projeto precisa controlar e documentar explicitamente
desde o início.

## Decisão

Escrever uma configuração **própria e documentada** de Docker Compose
(`compose.yaml`, `docker/php/Dockerfile`, `docker/nginx/default.conf`),
sem usar `laravel/sail`.

## Consequências

- Controle total sobre extensões PHP instaladas, usuário não-root,
  UID/GID configuráveis, health checks reais e política de portas
  (tudo vinculado a `127.0.0.1`).
- Mais texto de configuração para manter, mas nenhuma "mágica" escondida
  em um pacote Composer de terceiros.
- `docker/scripts/bootstrap.sh` e o `Makefile` cobrem a experiência de
  "um comando para começar" que o Sail normalmente oferece.
- Novos serviços (ex.: Horizon, Reverb, Octane) podem ser adicionados ao
  `compose.yaml` nas próximas fases sem depender de convenções do Sail.
