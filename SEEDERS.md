# Seeders

Este documento descreve os seeders do projeto: o que cada um popula, em que
ordem rodam, quais credenciais de teste eles geram e como resetar/re-rodar
com segurança. Todos os seeders aqui descritos são **exclusivos de
desenvolvimento/teste** — cada um tem uma guarda própria (`app()->isProduction()`
e, em alguns casos, uma variável de ambiente adicional) que impede qualquer
criação de dado em produção.

## Ordem de execução (`db:seed` padrão)

`database/seeders/DatabaseSeeder.php` encadeia, nesta ordem:

1. **`PlatformAdminSeeder`** — opcional. Só cria um administrador da
   plataforma (`is_platform_admin=true`) se `SEED_PLATFORM_ADMIN=true` e
   `PLATFORM_ADMIN_NAME`/`PLATFORM_ADMIN_EMAIL`/`PLATFORM_ADMIN_PASSWORD`
   estiverem todos definidos no `.env`. Bloqueado em produção. Prefira
   `php artisan app:create-platform-admin` para criar esse usuário fora de
   seeders.
2. **`SiteSettingSeeder`** — garante que a landing pública (`/`) sempre
   tenha algum conteúdo mínimo (`SiteSetting`, via `firstOrCreate`). Roda em
   qualquer ambiente, inclusive produção — não cria usuários nem dado de
   negócio.
3. **`DemoOrganizationSeeder`** — cria (ou corrige) **1 organização** de
   demonstração ("Clínica Exemplo") com unidade matriz e os 2 usuários de
   referência do ambiente local: `admin@admin.com` (administrador técnico)
   e o e-mail definido em `DEMO_CLINIC_ADMIN_EMAIL` (administrador/dono da
   clínica). Só cria o administrador da clínica se `DEMO_CLINIC_ADMIN_PASSWORD`
   estiver definida no `.env`. Bloqueado em produção.
4. **`DemoOperationalDataSeeder`** — popula o lado operacional (especialidades,
   serviços, produtos, profissionais, pacientes, agendamentos, prontuários,
   vendas, solicitações de agendamento e lista de espera) **da mesma
   organização** criada por `DemoOrganizationSeeder`. Só roda se essa
   organização e `DEMO_CLINIC_ADMIN_PASSWORD` já existirem. Bloqueado em
   produção.
5. **`LandingContentDemoSeeder`** — popula um modelo fictício de conteúdo de
   landing page (serviços, depoimentos, FAQ, galeria, profissionais em
   destaque). Bloqueado em produção.

**`QaDatasetSeeder` não faz parte dessa cadeia automática** — ver seção
própria abaixo. Isso é deliberado: `tests/Feature/Console/DatabaseSeederTest.php`
garante que um `db:seed` "puro" (sem nenhuma variável de ambiente de
desenvolvimento configurada, como em CI) nunca cria usuário nenhum; encadear
`QaDatasetSeeder` automaticamente quebraria essa garantia, já que ele não
depende de nenhuma variável de ambiente para decidir se roda.

## `QaDatasetSeeder` — massa de dados para teste manual/QA

Cria **2 organizações completas e totalmente independentes** (nenhum
paciente, agendamento, venda, profissional ou usuário é compartilhado entre
elas), cada uma com no mínimo 5 registros de cada tipo de entidade relevante
do domínio. Pensado para validar manualmente telas e fluxos com dados
realistas, sem depender do fluxo de bootstrap único do `DemoOrganizationSeeder`.

> ⚠️ **Nunca rode este seeder numa base que também hospede uma organização
> "real" (demonstração ou produção)**. O site público e o autocadastro do
> portal do paciente resolvem "a" organização da instalação assumindo que
> existe no máximo uma (instalação single-tenant, ver
> `docs/decisions/ADR-010-single-tenant-install-and-seo.md` e
> `App\Queries\CurrentInstallationOrganizationQuery`). Como este seeder
> sempre cria 2 organizações, ele **se recusa a rodar** (guarda em
> `QaDatasetSeeder::run()`) sempre que já existir qualquer organização fora
> dos slugs `qa-alfa`/`qa-beta` na base — para evitar reproduzir o cenário
> que motivou essa guarda: o site público passou a servir dados de "Clínica
> Alfa QA" para quem visitava o site de uma organização de demonstração
> real, porque a resolução de organização do front público não tinha como
> saber qual das organizações da base era "a" instalação. Use sempre uma
> base dedicada a QA para este seeder.

Rodar isoladamente:

```bash
make artisan cmd="db:seed --class=QaDatasetSeeder"
```

Bloqueado em produção (`app()->isProduction()`) — não depende de nenhuma
outra variável de ambiente.

### O que cada organização recebe

| Entidade | Quantidade | Observações |
|---|---|---|
| Unidades (`Unit`) | 5 | 1 matriz + 4 filiais |
| Vínculos de organização (`OrganizationMembership`) | 5 | 1 por papel: proprietário, administrador da clínica, gerente de unidade, recepção, financeiro |
| Especialidades (`Specialty`) | 5 | |
| Serviços (`Service`) | 5 | 1 por especialidade, todos públicos |
| Produtos (`Product`) | 5 | |
| Profissionais (`Professional`) | 5 | cada um com usuário próprio, registro de conselho, especialidade principal, serviço vinculado, unidade principal e jornada de trabalho (5 dias) |
| Pacientes (`Patient`) | 6 | 5 adultos + 1 menor de idade com responsável legal (`PatientResponsible`, RN-004) |
| Contatos de emergência (`PatientEmergencyContact`) | 6 | **todos** os pacientes têm um, não só uma amostra |
| Agendamentos (`Appointment`) | 15 | 5 concluídos, 5 confirmados (futuro), 3 cancelados, 2 faltas — 4 status diferentes |
| Prontuários (`MedicalRecord`) | 5 | 1 por agendamento concluído, finalizado |
| Vendas (`Sale`) | 6 | 5 confirmadas (1 delas com desconto) + 1 cancelada (equivalente a estorno — o domínio não tem status "estornado", ver `App\Enums\SaleStatus`) |
| Solicitações de agendamento (`AppointmentRequest`) | 5 | status variados: pendente (x2), contatado, agendado, cancelado |
| Lista de espera (`WaitlistEntry`) | 5 | 4 aguardando, 1 cancelada |

Ao todo, cada organização recebe 10 usuários com login: 5 de papéis
administrativos/operacionais + 5 vinculados a profissionais.

### Credenciais de teste geradas (⚠️ apenas ambiente local/dev — nunca produção)

Senha única para todos os usuários criados por este seeder:

```
QaTeste@123
```

**Clínica Alfa QA** (slug `qa-alfa`):

| Papel | E-mail |
|---|---|
| Proprietária | `proprietaria@qa-alfa.qa.test` |
| Administradora da clínica | `admin@qa-alfa.qa.test` |
| Gerente de unidade | `gerente@qa-alfa.qa.test` |
| Recepcionista | `recepcao@qa-alfa.qa.test` |
| Financeiro | `financeiro@qa-alfa.qa.test` |
| Profissionais | `profissional0@qa-alfa.qa.test` … `profissional4@qa-alfa.qa.test` |

**Clínica Beta QA** (slug `qa-beta`): mesmo padrão, trocando `qa-alfa` por
`qa-beta` em todos os e-mails acima (ex.: `admin@qa-beta.qa.test`).

### Idempotência e reset

Idempotente por coleção, no mesmo padrão de `DemoOperationalDataSeeder`:
cada etapa verifica se a organização já tem registros daquele tipo antes de
criar, então rodar o comando várias vezes **nunca duplica dados** — só
preenche o que ainda estiver faltando.

Para resetar por completo e gerar uma massa nova do zero, apague as duas
organizações (isso arrasta, por FK, todos os registros dependentes) e rode o
seeder novamente:

```bash
make artisan cmd="tinker --execute 'App\Models\Organization::whereIn(\"slug\", [\"qa-alfa\", \"qa-beta\"])->get()->each->forceDelete();'"
make artisan cmd="db:seed --class=QaDatasetSeeder"
```

> Este `forceDelete` é seguro porque atinge exclusivamente as organizações
> `qa-alfa`/`qa-beta` criadas por este seeder de teste — nunca use
> `forceDelete` em dados de negócio reais (ver regra de exclusão lógica no
> `CLAUDE.md`).

## Outros seeders — credenciais de referência

**`DemoOrganizationSeeder`** (requer `DEMO_CLINIC_ADMIN_PASSWORD` no `.env`):

| Papel | E-mail | Senha |
|---|---|---|
| Administrador técnico (platform admin) | `admin@admin.com` | definida manualmente por quem administra o ambiente local — nunca criada com senha padrão por seeder |
| Administrador/dono da clínica | valor de `DEMO_CLINIC_ADMIN_EMAIL` (padrão `admin@gestao-clinicas.local`) | valor de `DEMO_CLINIC_ADMIN_PASSWORD` |

**`PlatformAdminSeeder`** (requer `SEED_PLATFORM_ADMIN=true` + `PLATFORM_ADMIN_*`):
cria um administrador da plataforma com nome/e-mail/senha vindos das
variáveis `PLATFORM_ADMIN_NAME`/`PLATFORM_ADMIN_EMAIL`/`PLATFORM_ADMIN_PASSWORD`
do `.env` — nenhuma credencial fixa no código.
