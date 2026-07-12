# Gestão de Clínicas — Instruções para agentes de IA

Este projeto usa Laravel Boost para gerar guidelines detalhadas específicas
da versão de cada pacote instalado, disponíveis em `CLAUDE.md`. Este arquivo
(`AGENTS.md`) contém as mesmas regras de projeto para ferramentas que não
leem `CLAUDE.md`.

## Stack

- Backend: Laravel 13, PHP 8.4, Composer 2, Laravel Fortify, Pest, Laravel Pint, Larastan/PHPStan, Laravel Boost.
- Administração: Filament 5, Livewire 4.
- Frontend: Inertia.js 3, Vue 3 (Composition API), TypeScript, Tailwind CSS, shadcn-vue, Vite, ESLint, Prettier, vue-tsc, Vitest, Vue Test Utils.
- Infraestrutura: Nginx, PostgreSQL 17, Redis (phpredis), MinIO (S3), Mailpit, Docker Compose (sem Laravel Sail).

## Comandos Docker

```bash
make init            # prepara o projeto do zero (idempotente)
make up / make down   # sobe/derruba os containers
make shell            # shell no container app
make artisan cmd="…"  # php artisan …
make composer cmd="…" # composer …
make npm cmd="…"      # npm … (container node)
make migrate          # migrations pendentes (nunca fresh)
make doctor           # diagnóstico completo da infraestrutura
```

## Comandos de qualidade

```bash
make lint      # Pint --test + ESLint
make format    # Pint + Prettier (aplica correções)
make analyse   # Larastan/PHPStan + vue-tsc
make test      # Pest + Vitest
composer quality   # lint + analyse + test (backend)
npm run quality    # lint + format:check + types + test:run (frontend)
```

## Arquitetura

- Estrutura modular em `app/` (Actions, Data, Enums, Events, Jobs, Models, Notifications, Observers, Policies, Queries, Rules, Services, Support) e `resources/js/` (components, composables, layouts, lib, pages, types).
- Rotas organizadas por contexto em `routes/`: `public-site.php`, `clinic.php`, `patient-portal.php`, `platform.php`, carregadas explicitamente por `web.php`.
- O painel Filament (`/admin`) é a única forma de administração da plataforma nesta fase; não duplique rotas dele em `platform.php`.

## Regras obrigatórias

- **Datas e horários**: sempre armazenar em UTC (`APP_TIMEZONE=UTC`). A apresentação usa o fuso da organização/unidade (`config('business.default_timezone')`, padrão `America/Sao_Paulo`). Nunca grave um valor já convertido para o horário local da clínica.
- **Sem lógica de negócio em Controllers**: delegue para Actions/Services. Controllers apenas orquestram request → Action/Service → response.
- **Sempre use Form Requests** para validação de entrada em vez de validar diretamente no Controller.
- **Sempre use Policies** para autorização de acesso a dados de negócio.
- **Nunca exclusão física de dados de negócio** nas fases futuras — prefira soft delete ou arquivamento, uma vez que os módulos de negócio existam.
- **Nunca acesse dados de outra organização** — toda query de domínio deverá respeitar o isolamento entre organizações/unidades assim que o módulo de tenancy existir (`app/Support/Tenancy`).
- **Nunca compartilhe senhas** — nem em código, nem em commits, nem em logs, nem em respostas de comando (ver `app:create-platform-admin`).
- **Nunca registre dados sensíveis em logs** (senhas, tokens, chaves, dados de pacientes).
- **Sempre crie testes** para código novo (Pest no backend, Vitest no frontend) e rode a suíte afetada antes de considerar uma tarefa concluída.
- **Consulte a documentação da versão instalada** antes de usar uma API de pacote — não invente métodos, opções ou assinaturas que não existem na versão instalada.
- **Nunca execute migrations destrutivas sem autorização explícita** do usuário (`migrate:fresh`, `migrate:rollback` em dados com informação real, `drop`).
- **Não altere arquivos fora do escopo da tarefa** solicitada.
- **Execute os testes relevantes antes de concluir uma tarefa** e reporte o resultado real, nunca presuma sucesso.
