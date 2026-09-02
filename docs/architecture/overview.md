# Visão geral da arquitetura

## Objetivo desta fase

Fundação técnica do SaaS de gestão de clínicas e consultórios, mais a
**Fase 1** (multiempresa): organizações, entidades legais (CPF/CNPJ),
unidades, endereços, horários de funcionamento e contexto ativo — ver
[tenancy.md](tenancy.md), [auditing.md](auditing.md),
[localization.md](localization.md) e [docs/modules/](../modules/). A
fundação de domínio próprio, SEO técnico e acessibilidade da página
pública inicial está em [seo.md](seo.md), e a matriz de papéis e
permissões (RBAC) em [permissions.md](permissions.md). A página pública
(landing, galeria em `/galeria`, benefícios, seções, SEO) e o perfil do
usuário (dados pessoais, endereço, foto) foram concluídos na Fase 0.9.
Profissionais, pacientes, agenda, prontuário, financeiro, produtos,
estoque e vendas ainda não foram implementados — ver
[docs/pre-agendamento-futuro.md](../pre-agendamento-futuro.md) para a
arquitetura (não implementada) da futura busca de clínicas e
pré-agendamento.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Autenticação | Laravel Fortify (nativo, sem pacotes de terceiros) |
| Administração | Filament 5 + Livewire 4, painel em `/admin` |
| Frontend | Inertia.js 3 + Vue 3 (Composition API) + TypeScript |
| UI | Tailwind CSS + shadcn-vue |
| Banco de dados | PostgreSQL 17 |
| Cache/Sessão/Fila | Redis (phpredis) |
| Storage de objetos | MinIO (compatível com S3), disco `s3` do Laravel |
| E-mail (dev) | Mailpit |
| Orquestração local | Docker Compose (sem Laravel Sail) |
| Qualidade | Pint, Larastan/PHPStan, Pest, ESLint, Prettier, vue-tsc, Vitest |
| IA assistida | Laravel Boost (Claude Code) |

## Monólito modular

O projeto é um monólito Laravel único (ver [ADR-001](../decisions/ADR-001-monolith.md)),
organizado em módulos internos dentro de `app/` e `resources/js/`. Não há
microsserviços, API REST/GraphQL, nem separação de deploy nesta fase.

## Estrutura de pastas (backend)

```
app/
├── Actions/      # casos de uso únicos (ex.: Fortify\CreateNewUser)
├── Data/         # DTOs tipados
├── Enums/        # enums de domínio
├── Events/       # eventos de domínio
├── Jobs/         # jobs de fila (ex.: InfrastructureSmokeJob)
├── Models/       # models Eloquent
├── Notifications/
├── Observers/
├── Policies/     # autorização de acesso a dados de negócio
├── Queries/      # objetos de consulta reutilizáveis
├── Rules/        # regras de validação customizadas
├── Services/     # integrações e regras que não cabem em uma Action
└── Support/
    ├── Auditing/ # ponto de extensão para auditoria futura
    ├── Money/    # ponto de extensão para valores monetários
    ├── Tenancy/  # ponto de extensão para isolamento entre organizações
    └── Uploads/  # ponto de extensão para upload de arquivos clínicos
```

Pastas ainda sem implementação contêm apenas um `README.md` explicando sua
finalidade — não crie classes vazias "para o futuro".

## Estrutura de pastas (frontend)

```
resources/js/
├── components/  # componentes Vue (inclui components/ui, shadcn-vue)
├── composables/ # composables Vue
├── layouts/     # layouts Inertia
├── lib/         # utilitários (ex.: cn())
├── pages/       # páginas Inertia
└── types/       # tipos TypeScript compartilhados
```

## Rotas

Rotas organizadas por contexto (ver [docs/architecture/development.md](development.md)):

- `routes/public-site.php` — site público (`/`).
- `routes/clinic.php` — área autenticada da equipe da clínica (`/dashboard`).
- `routes/patient-portal.php` — reservado para o futuro portal do paciente.
- `routes/platform.php` — reservado para rotas administrativas fora do Filament.
- `routes/settings.php` — perfil e segurança do usuário autenticado.

`routes/web.php` carrega todos os arquivos acima explicitamente.

## Regra de fuso horário

Datas e horários são **sempre armazenados em UTC** (`APP_TIMEZONE=UTC`). A
apresentação usa o fuso horário do negócio, configurado em
`config/business.php` (`BUSINESS_DEFAULT_TIMEZONE`, padrão
`America/Sao_Paulo`). Futuramente, cada organização/unidade poderá ter seu
próprio fuso — a coluna de armazenamento nunca deve conter um valor já
convertido para horário local.
