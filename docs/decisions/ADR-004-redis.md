# ADR-004: Redis para cache, sessão e fila

## Status

Aceito.

## Contexto

O sistema precisa de cache rápido, sessões compartilhadas entre processos
PHP-FPM e um mecanismo de filas confiável para trabalho assíncrono
(notificações, jobs de manutenção), sem introduzir um message broker
dedicado nesta fase.

## Decisão

Usar **Redis** (via extensão `phpredis`) como driver de `CACHE_STORE`,
`SESSION_DRIVER` e `QUEUE_CONNECTION`, protegido por senha obrigatória e
sem exposição de porta pública.

## Consequências

- Um único serviço de infraestrutura cobre três responsabilidades
  (cache/sessão/fila), simplificando o Docker Compose desta fase.
- `queue:work redis` processa jobs; `schedule:work` cuida do agendamento —
  sem cron no container.
- Caso o volume de filas cresça muito, uma migração futura para um broker
  dedicado (ex.: SQS, RabbitMQ) fica em aberto, mas não é necessária agora.
- Laravel Horizon não foi instalado nesta fase (reservado para avaliação
  futura).
