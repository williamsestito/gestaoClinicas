# Testes

## Backend (Pest)

- Banco de dados de testes: **PostgreSQL real e separado**
  (`DB_TEST_DATABASE`, padrão `gestao_clinicas_test`), nunca SQLite — o
  projeto depende de recursos específicos do PostgreSQL nas próximas fases.
- `phpunit.xml` define apenas `APP_ENV=testing`; as demais variáveis
  (`DB_*`, `REDIS_*`, etc.) vêm de `.env.testing`, gerado automaticamente
  (e de forma idempotente) pelo entrypoint a partir do `.env` local —
  nunca é commitado.
- `tests/Pest.php` aplica `RefreshDatabase` a todos os testes em
  `tests/Feature`.
- Rodar: `make test-backend` ou `docker compose exec app php artisan test`.

### Cobertura obrigatória desta fase

| Área | Arquivo |
|---|---|
| Página inicial acessível | `tests/Feature/ExampleTest.php` |
| Guest redirecionado do dashboard | `tests/Feature/DashboardTest.php` |
| Usuário verificado acessa o dashboard | `tests/Feature/DashboardTest.php` |
| Usuário não verificado é bloqueado | `tests/Feature/DashboardTest.php` |
| Usuário comum não acessa o Filament | `tests/Feature/Filament/PlatformAdminPanelAccessTest.php` |
| Usuário inativo não acessa o Filament | `tests/Feature/Filament/PlatformAdminPanelAccessTest.php` |
| Administrador da plataforma acessa o Filament | `tests/Feature/Filament/PlatformAdminPanelAccessTest.php` |
| Comando `app:create-platform-admin` | `tests/Feature/Console/CreatePlatformAdminCommandTest.php` |
| Comando `app:doctor` | `tests/Feature/Console/AppDoctorCommandTest.php` |
| Conexão e operação no banco de testes | `tests/Feature/Infrastructure/DatabaseConnectionTest.php` |
| Cache Redis | `tests/Feature/Infrastructure/RedisCacheTest.php` |
| Disco local | `tests/Feature/Infrastructure/StorageDisksTest.php` |
| Configuração do disco MinIO/S3 | `tests/Feature/Infrastructure/StorageDisksTest.php` |
| Fila configurada para Redis | `tests/Feature/Infrastructure/QueueConfigurationTest.php` |
| Job de smoke test da fila | `tests/Feature/Infrastructure/InfrastructureSmokeJobTest.php` |
| `/up` responde | `tests/Feature/UpEndpointTest.php` |

Testes que dependem de Postgres/Redis reais e alcançáveis (fora do
contexto de CI/Docker) usam `->skip(...)` com uma mensagem clara, em vez de
falhar sem explicação.

## Frontend (Vitest + Vue Test Utils)

- Ambiente `jsdom`, configurado em `vitest.config.ts`.
- Rodar: `make test-frontend` ou `npm run test:run` (modo watch: `npm run test`).
- Exemplo: `resources/js/components/ui/badge/Badge.spec.ts`.

## Qualidade estática

- `make lint` — Pint em modo de verificação + ESLint.
- `make analyse` — Larastan/PHPStan (nível 7) + vue-tsc.
- `make format` — aplica Pint + Prettier.
