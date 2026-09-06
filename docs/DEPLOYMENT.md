# CI/CD e Deploy

Este documento é a referência de **como o pipeline funciona** e **o que precisa
ser configurado manualmente no GitHub/VPS** — quem já sabe operar a VPS pode
sair direto para
[docs/README_INFRAESTRUTURA_VPS_GESTAO_CLINICAS.md](README_INFRAESTRUTURA_VPS_GESTAO_CLINICAS.md),
que cobre Traefik, Cloudflare, firewall, fail2ban e a estrutura de diretórios
da VPS em detalhe. Este arquivo não repete aquele conteúdo.

## 1. Fluxo

```text
desenvolvimento (local)
    ↓ git push origin beta
beta
    ↓
Pull Request beta → main
    ↓
CI (.github/workflows/ci.yml) — obrigatório, bloqueia merge se falhar
    ↓ merge aprovado
main
    ↓ push (o próprio merge)
CI roda de novo em main
    ↓ CI passou
Deploy (.github/workflows/deploy.yml) — só dispara com CI verde em main
    ↓ SSH
VPS: scripts/deploy.sh
    ↓
build → up → migrations → optimize → queue:restart → health check (/up)
    ↓ falhou o health check?
rollback automático do código (nunca do banco) para o commit anterior
```

Nenhum deploy é disparado a partir de `beta`, de um PR, ou de qualquer branch
que não seja `main`. `beta` = desenvolvimento/homologação; `main` = produção.

## 2. Branch strategy (resumo)

```bash
git checkout beta
git pull origin beta
# ... trabalhar, commitar ...
git push origin beta
# depois: abrir PR beta -> main no GitHub
```

Nunca `git push origin main` como fluxo normal — sempre via PR + CI verde.

**Resolver conflitos do PR beta → main:**

```bash
git checkout beta
git fetch origin
git merge origin/main
# resolver os conflitos
git add .
git commit
git push origin beta
```

O CI roda de novo automaticamente após o push.

## 3. CI (`.github/workflows/ci.yml`)

Dispara em:

- `pull_request` com destino `main` (inclui `beta → main`);
- `push` em `main` (o próprio merge do PR);
- `workflow_dispatch` (execução manual).

Etapas: checkout → PHP 8.4 → Node 24 → cache Composer → `.env.testing` →
`composer install` → `npm ci` → `key:generate` → build do frontend →
PostgreSQL/Redis de teste → migrations → **Pint** → **Larastan/PHPStan** →
**Pest** → **ESLint** → **Prettier** → **vue-tsc** → **Vitest**.

O job se chama `ci` (arquivo tem `name: CI`, job `ci` sem `name:` próprio) —
esse é o nome exato que aparece na lista de status checks do GitHub.

## 4. Deploy (`.github/workflows/deploy.yml`)

Dispara via `workflow_run` **depois** que o workflow `CI` terminar rodando em
`main` — e só segue se `github.event.workflow_run.conclusion == 'success'`.
Isso já implementa, entre workflows separados, o mesmo efeito de um `needs:
[ci]`: falha no CI → deploy nunca executa. Nunca reexecuta a suíte de testes.

`concurrency: { group: deploy-production, cancel-in-progress: false }` evita
dois deploys simultâneos na mesma VPS sem cancelar um deploy já em
andamento.

O job usa `environment: { name: production }` (ver seção 6) e faz SSH até a
VPS via [appleboy/ssh-action](https://github.com/appleboy/ssh-action) (pinado
por SHA de commit, não por tag), depois roda `scripts/deploy.sh` — toda a
lógica de deploy vive nesse script, não no YAML.

### `scripts/deploy.sh`

Funções, na ordem em que rodam:

1. `validate_environment` — confere `docker`, `compose.yaml`,
   `compose.prod.yaml` e `.env` de produção (nunca cria/edita esse arquivo).
2. `backup_current_commit` — guarda o commit atual (`PREVIOUS_COMMIT`) antes
   de qualquer mudança, para permitir rollback.
3. `update_repository` — `git fetch origin main && git reset --hard
   origin/main`. `reset --hard` só descarta mudanças em arquivos
   **rastreados** pelo git — nunca apaga `.env`, `storage/`, uploads ou
   qualquer outro arquivo não versionado.
4. `build_containers` / `start_containers` — `docker compose -f compose.yaml
   -f compose.prod.yaml build` e `up -d --remove-orphans --wait` (o `--wait`
   já bloqueia até os healthchecks ficarem OK, sem loop manual).
5. `build_frontend_assets` — `npm run build` dentro do container `node`.
6. `run_migrations` — `php artisan migrate --force`.
7. `optimize_laravel` — `optimize:clear` seguido de `optimize`
   (config/route/view/event cache).
8. `restart_workers` — `php artisan queue:restart`: o worker (`queue:work`)
   termina o job atual e sai; `restart: unless-stopped` sobe o container de
   novo já com o código novo. O scheduler não precisa disso — cada tarefa
   agendada roda em um processo `php artisan` novo.
9. `health_check` — `curl` repetido contra a rota padrão do Laravel `/up`
   (`https://gestao.espacodudaalmeida.com.br/up`).
10. Se qualquer etapa acima falhar (não só o health check — qualquer erro,
    via `trap ... ERR`): imprime `docker compose ps` + últimas 100 linhas de
    log de cada serviço (nunca o `.env`) e chama `rollback_code`.

### Rollback

`rollback_code` volta o código para `PREVIOUS_COMMIT` (`git reset --hard`),
reconstrói e sobe os containers, recompila o frontend, reotimiza e reinicia
os workers — **nunca** roda `migrate:rollback` automaticamente (risco real
de perda de dados se a migration já alterou dados). Se a versão que falhou
adicionou migrations incompatíveis com o código anterior, isso fica registrado
como aviso no log e precisa de revisão manual antes do próximo deploy.
Prefira sempre migrations backward-compatible.

**Nunca aparece no deploy:** `migrate:fresh`, `migrate:rollback` automático,
`db:wipe`, `docker compose down -v`, remoção de volume, `FLUSHALL` no Redis,
`docker system prune`.

## 5. Secrets necessários (GitHub → Settings → Secrets and variables → Actions)

| Secret | Descrição |
|---|---|
| `VPS_HOST` | IP ou hostname da VPS |
| `VPS_PORT` | Porta SSH (normalmente `22`) |
| `VPS_USER` | Usuário SSH sem privilégios root (ex.: `deploy`) |
| `VPS_SSH_KEY` | Chave privada SSH correspondente a uma chave pública em `~/.ssh/authorized_keys` do `VPS_USER` |
| `VPS_HOST_FINGERPRINT` | Fingerprint SHA256 da host key da VPS (ver comando abaixo) — pina o servidor esperado em vez de aceitar qualquer host key na primeira conexão |

Nenhum valor real vai neste repositório. Gerar o fingerprint a partir de uma
máquina que já confia na VPS:

```bash
ssh <VPS_HOST> ssh-keygen -l -f /etc/ssh/ssh_host_ed25519_key.pub | cut -d ' ' -f2
```

(troque `ed25519` pelo tipo de chave real do host, se for diferente).

Se algum outro secret vier a ser necessário no futuro (ex.: token de um
serviço de notificação de deploy), documentar aqui antes de usar — nunca
inventar nomes de secret que não existem no repositório.

## 6. GitHub Environment `production`

Em **Settings → Environments → New environment**, criar `production` e
associar os 5 secrets da seção anterior a ele (em vez de/além de secrets do
repositório). Isso permite, quando desejado:

- exigir aprovação manual (reviewers) antes do job de deploy rodar;
- restringir quais branches podem fazer deploy através deste Environment
  (`main` apenas);
- ver o histórico de deploys na aba "Environments" do repositório.

Este passo é 100% manual no GitHub — o workflow já referencia
`environment: production`, mas o Environment em si precisa existir.

## 7. Ruleset da branch `main` (Settings → Rules → Rulesets)

Criar um Ruleset (ou Branch Protection clássica, se o plano não tiver
Rulesets) chamado por exemplo `Protect main`, alvo `main`, com:

- **Require a pull request before merging** — ligado.
- **Require status checks to pass** — ligado, com o check obrigatório
  **`ci`** (nome exato do job em `ci.yml`; confirme abrindo um PR de teste e
  olhando a lista de checks disponíveis, caso o GitHub exiba um nome
  ligeiramente diferente).
- **Require branches to be up to date before merging** — ligado.
- **Require conversation resolution before merging** — ligado.
- **Block force pushes** — ligado.
- **Restrict deletions** — ligado.

Planos gratuitos de repositórios privados podem ter limitações em Rulesets
avançados (ex.: nº de regras, required reviewers) — se algo aqui não
aparecer disponível, o Branch Protection clássico (Settings → Branches →
Add rule) cobre os mesmos itens.

## 8. Deploy Key da VPS (leitura, VPS → GitHub)

Na VPS, gerar uma chave dedicada e cadastrá-la em **Settings → Deploy keys**
como **somente leitura**:

```bash
ssh-keygen -t ed25519 -C "gestao-clinicas-vps-deploy" -f ~/.ssh/github_repo
cat ~/.ssh/github_repo.pub   # colar em Settings -> Deploy keys
```

Detalhes de configuração do `~/.ssh/config` para essa chave estão em
[docs/README_INFRAESTRUTURA_VPS_GESTAO_CLINICAS.md §14](README_INFRAESTRUTURA_VPS_GESTAO_CLINICAS.md).
Nunca usar um Personal Access Token nem colar credenciais Git no workflow.

## 9. Preparar uma VPS nova (resumo)

```bash
# usuário de deploy, sem root, no grupo docker
adduser deploy
usermod -aG docker deploy

# diretório do projeto
mkdir -p /opt/apps/gestao-clinicas
chown deploy:deploy /opt/apps/gestao-clinicas

# chave pública do GitHub Actions (conteúdo de VPS_SSH_KEY) vai aqui:
# /home/deploy/.ssh/authorized_keys

# clonar via a Deploy Key (seção 8) e então:
cd /opt/apps/gestao-clinicas
cp .env.example .env   # preencher com valores reais de produção, nunca commitar
make prod-build
docker compose -f compose.yaml -f compose.prod.yaml up -d postgres redis minio
docker compose -f compose.yaml -f compose.prod.yaml run --rm --no-deps app \
  composer install --no-dev --optimize-autoloader --no-interaction
docker compose -f compose.yaml -f compose.prod.yaml run --rm --no-deps app \
  php artisan key:generate --force
make prod-up
make prod-migrate
make prod-optimize
make prod-status
```

Traefik, rede `proxy`, DNS/Cloudflare e SSL são pré-requisitos de
infraestrutura cobertos em
[docs/README_INFRAESTRUTURA_VPS_GESTAO_CLINICAS.md](README_INFRAESTRUTURA_VPS_GESTAO_CLINICAS.md)
— este script de deploy não gerencia nada disso.

## 10. `.env` de produção

- Vive **somente** na VPS (`/opt/apps/gestao-clinicas/.env`, `chmod 600`).
- Está no `.gitignore` (`.env`, `.env.*`, exceto `.env.example`) — nunca é
  commitado, nunca aparece no GitHub Actions, nunca é sobrescrito pelo
  deploy (`scripts/deploy.sh` apenas verifica que ele existe).
- `.env.example` não contém credenciais reais — todas as variáveis
  sensíveis (senhas, chaves, tokens) estão vazias, preenchidas apenas na
  VPS.

## 11. O que ainda depende de ação manual

- Criar os 5 secrets (seção 5) no GitHub.
- Criar o Environment `production` (seção 6) e, se desejado, configurar
  aprovação manual.
- Configurar o Ruleset de `main` (seção 7).
- Cadastrar a Deploy Key da VPS (seção 8), se ainda não existir.
- Gerar e cadastrar `VPS_HOST_FINGERPRINT` (seção 5).
- Definir rotina de backup (Postgres/MinIO/Traefik) — ainda pendente, ver
  [docs/README_INFRAESTRUTURA_VPS_GESTAO_CLINICAS.md §28](README_INFRAESTRUTURA_VPS_GESTAO_CLINICAS.md).

Nenhuma dessas ações foi executada por este agente — exigem acesso a
credenciais e configuração real do GitHub/VPS.
