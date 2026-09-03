# README — Infraestrutura, Acesso e Continuidade do Projeto Gestão de Clínicas

Este documento reúne as informações necessárias para que outra pessoa consiga acessar a VPS, entender a infraestrutura, operar o ambiente, atualizar o projeto e continuar o trabalho com o mínimo de dependência de contexto prévio.

> **Importante:** este documento não contém senhas, tokens, chaves privadas ou outros segredos. Essas credenciais devem permanecer apenas nos cofres/Secrets apropriados.

---

## 1. Visão geral da infraestrutura

```text
Registro.br
   ↓
Cloudflare DNS
   ↓
VPS
   ↓
Traefik
   ↓
Docker
   ↓
Gestão de Clínicas
```

- Domínio: `espacodudaalmeida.com.br`
- Aplicação: `gestao.espacodudaalmeida.com.br`
- VPS: BorkCloud
- SO: Ubuntu 26.04 LTS x86_64
- Docker Engine + Docker Compose Plugin
- Reverse proxy: Traefik 3.6.x
- Aplicação: Laravel + Vue/Vite
- Banco: PostgreSQL 17
- Cache/Fila: Redis 7
- Storage: MinIO
- CI: GitHub Actions
- Repositório: `git@github.com:williamsestito/gestaoClinicas.git`

---

## 2. Dados da VPS

IP público:

```text
209.61.39.221
```

Usuário administrativo:

```text
deploy
```

O login direto como `root` via SSH está bloqueado.

Para obter shell root:

```bash
sudo -i
```

Ou:

```bash
sudo comando
```

---

## 3. Acesso SSH

Acesso principal:

```bash
ssh deploy@209.61.39.221
```

Também pode ser utilizado, se o DNS estiver resolvendo:

```bash
ssh deploy@srv01.espacodudaalmeida.com.br
```

O servidor usa autenticação por chave pública.

Hardening aplicado:

```text
PermitRootLogin no
PubkeyAuthentication yes
PasswordAuthentication no
KbdInteractiveAuthentication no
```

Arquivo:

```text
/etc/ssh/sshd_config.d/00-hardening.conf
```

Validar:

```bash
sudo sshd -t
```

Ver configuração efetiva:

```bash
sudo sshd -T | grep -E 'permitrootlogin|pubkeyauthentication|passwordauthentication|kbdinteractiveauthentication'
```

Esperado:

```text
permitrootlogin no
pubkeyauthentication yes
passwordauthentication no
kbdinteractiveauthentication no
```

---

## 4. Firewall

Status:

```bash
sudo ufw status verbose
```

Portas permitidas:

```text
22/tcp   SSH
80/tcp   HTTP
443/tcp  HTTPS
```

Não abrir publicamente:

```text
5432 PostgreSQL
6379 Redis
9000 MinIO API
9001 MinIO Console
5173 Vite
```

---

## 5. Fail2ban

Validar:

```bash
sudo systemctl is-active fail2ban
```

Esperado:

```text
active
```

---

## 6. Docker

```bash
docker --version
docker compose version
sudo systemctl is-active docker
```

O usuário `deploy` pertence ao grupo Docker.

Validar:

```bash
groups
```

---

## 7. Estrutura da VPS

```text
/opt/
├── traefik/
├── apps/
│   ├── landing/
│   └── gestao-clinicas/
└── backups/
```

Traefik:

```text
/opt/traefik
```

Landing de teste:

```text
/opt/apps/landing
```

Gestão de Clínicas:

```text
/opt/apps/gestao-clinicas
```

---

## 8. Rede Docker

Existe uma rede externa chamada:

```text
proxy
```

Validar:

```bash
docker network ls
```

Criar novamente, se necessário:

```bash
docker network create proxy
```

---

## 9. Traefik

Diretório:

```bash
cd /opt/traefik
```

Estrutura:

```text
/opt/traefik/
├── docker-compose.yml
├── .env
└── letsencrypt/
    └── acme.json
```

Permissões:

```bash
chmod 600 /opt/traefik/.env
chmod 600 /opt/traefik/letsencrypt/acme.json
```

Subir:

```bash
cd /opt/traefik
docker compose up -d
```

Parar:

```bash
docker compose down
```

Logs:

```bash
docker logs traefik --tail 100
```

Versão validada:

```text
Traefik 3.6.25
```

Traefik 3.5 apresentou incompatibilidade com Docker Engine 29.x; utilizar 3.6.x ou superior compatível.

---

## 10. SSL / Let's Encrypt / Cloudflare

O Traefik usa Let's Encrypt com DNS Challenge da Cloudflare.

Permissões mínimas do token:

```text
Zone → DNS → Edit
Zone → Zone → Read
```

Zona:

```text
espacodudaalmeida.com.br
```

O token deve permanecer somente em:

```text
/opt/traefik/.env
```

Nunca versionar.

---

## 11. Cloudflare

Nameservers:

```text
aurora.ns.cloudflare.com
kevin.ns.cloudflare.com
```

Registros esperados:

```text
A      @        209.61.39.221
CNAME  www      espacodudaalmeida.com.br
A      gestao   209.61.39.221
A      srv01    209.61.39.221
```

Durante a configuração foi identificado timeout de conectividade com IPs proxied da Cloudflare. Por isso, os registros foram temporariamente deixados como **DNS only** para validar acesso direto à VPS.

SSL/TLS:

```text
Full (strict)
```

Nunca usar `Flexible`.

---

## 12. Testes de conectividade

DNS autoritativo:

```bash
dig @aurora.ns.cloudflare.com espacodudaalmeida.com.br A +short
```

HTTP:

```bash
nc -vz 209.61.39.221 80
```

HTTPS:

```bash
nc -vz 209.61.39.221 443
```

HTTPS direto, ignorando DNS:

```bash
curl -Iv --connect-timeout 10 \
  --resolve gestao.espacodudaalmeida.com.br:443:209.61.39.221 \
  https://gestao.espacodudaalmeida.com.br
```

---

## 13. Git

Repositório:

```text
git@github.com:williamsestito/gestaoClinicas.git
```

Na VPS:

```bash
cd /opt/apps/gestao-clinicas
```

Validar:

```bash
git remote -v
git branch --show-current
```

Branch de produção:

```text
main
```

Atualização manual:

```bash
git fetch origin
git checkout main
git pull --ff-only origin main
```

Evitar `git reset --hard` em automações.

---

## 14. Deploy Key da VPS para GitHub

Chave:

```text
/home/deploy/.ssh/github_repo
```

Configuração:

```text
/home/deploy/.ssh/config
```

Conteúdo esperado:

```text
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/github_repo
    IdentitiesOnly yes
```

Permissão:

```bash
chmod 600 ~/.ssh/config
```

Teste:

```bash
ssh -T git@github.com
```

---

## 15. GitHub Actions → VPS

Chave dedicada:

```text
/home/deploy/.ssh/github_actions
```

A pública correspondente está em:

```text
/home/deploy/.ssh/authorized_keys
```

Repository Secrets:

```text
VPS_HOST
VPS_PORT
VPS_USER
VPS_SSH_KEY
```

Valores não secretos:

```text
VPS_HOST = 209.61.39.221
VPS_PORT = 22
VPS_USER = deploy
```

Nunca compartilhar `VPS_SSH_KEY`.

---

## 16. Compose do projeto

Arquivos:

```text
compose.yaml
compose.prod.yaml
Makefile
```

Desenvolvimento:

```bash
docker compose
```

Produção:

```bash
docker compose -f compose.yaml -f compose.prod.yaml
```

Validar sem expor secrets:

```bash
docker compose -f compose.yaml -f compose.prod.yaml config --quiet
```

---

## 17. `.env` de produção

Local:

```text
/opt/apps/gestao-clinicas/.env
```

Permissão:

```bash
chmod 600 .env
```

Principais variáveis:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gestao.espacodudaalmeida.com.br

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432

REDIS_HOST=redis
REDIS_PORT=6379

SESSION_SECURE_COOKIE=true

MAIL_MAILER=log

AWS_ENDPOINT=http://minio:9000
```

Nunca compartilhar a saída completa de:

```bash
docker compose config
```

Use:

```bash
docker compose -f compose.yaml -f compose.prod.yaml config --quiet
```

---

## 18. Makefile

Desenvolvimento:

```bash
make help
make init
make build
make up
make down
make restart
make ps
make logs
make shell
make artisan cmd="..."
make composer cmd="..."
make npm cmd="..."
make migrate
make seed
make test
make lint
make format
make analyse
make doctor
```

Produção:

```bash
make prod-build
make prod-up
make prod-down
make prod-restart
make prod-ps
make prod-status
make prod-logs
make prod-shell
make prod-artisan cmd="..."
make prod-migrate
make prod-seed
make prod-build-assets
make prod-optimize
make prod-optimize-clear
make prod-deploy
```

---

## 19. Primeiro bootstrap de produção

```bash
cd /opt/apps/gestao-clinicas
```

Build:

```bash
make prod-build
```

Subir dados:

```bash
docker compose -f compose.yaml -f compose.prod.yaml up -d postgres redis minio
```

Instalar dependências PHP:

```bash
docker compose -f compose.yaml -f compose.prod.yaml run --rm --no-deps app \
  composer install --no-dev --optimize-autoloader --no-interaction
```

Gerar APP_KEY:

```bash
docker compose -f compose.yaml -f compose.prod.yaml run --rm --no-deps app \
  php artisan key:generate --force
```

Build frontend:

```bash
docker compose -f compose.yaml -f compose.prod.yaml run --rm --no-deps node \
  sh -c "npm ci --include=dev && npm run build"
```

Subir tudo:

```bash
make prod-up
```

Migrations:

```bash
make prod-migrate
```

Otimizar:

```bash
make prod-optimize
```

Status:

```bash
make prod-status
```

---

## 20. Serviços

```text
app
nginx
node
postgres
redis
queue
scheduler
minio
minio-init
```

Mailpit é destinado ao desenvolvimento.

---

## 21. Nginx

Arquivo:

```text
docker/nginx/default.conf
```

PHP-FPM:

```text
app:9000
```

Erro já identificado:

```text
upstream sent too big header while reading response header from upstream
```

Buffers recomendados:

```nginx
fastcgi_buffer_size 32k;
fastcgi_buffers 16 32k;
fastcgi_busy_buffers_size 64k;
```

Após atualização:

```bash
git pull --ff-only origin main

docker compose -f compose.yaml -f compose.prod.yaml \
  up -d --force-recreate nginx
```

Logs:

```bash
docker compose -f compose.yaml -f compose.prod.yaml logs --tail=100 nginx
```

---

## 22. Logs

Todos:

```bash
make prod-logs
```

App:

```bash
make prod-logs service=app
```

Nginx:

```bash
make prod-logs service=nginx
```

Queue:

```bash
make prod-logs service=queue
```

Scheduler:

```bash
make prod-logs service=scheduler
```

Traefik:

```bash
docker logs traefik --tail 100
```

---

## 23. Diagnóstico de HTTP 502

```bash
docker compose -f compose.yaml -f compose.prod.yaml logs --tail=100 nginx
docker compose -f compose.yaml -f compose.prod.yaml logs --tail=100 app
make prod-status
```

---

## 24. Testes HTTP

```bash
curl -I https://gestao.espacodudaalmeida.com.br
curl -I https://espacodudaalmeida.com.br
```

---

## 25. CI GitHub Actions

Arquivo:

```text
.github/workflows/ci.yml
```

Ordem atual:

```text
Checkout
↓
PHP
↓
Node
↓
Composer
↓
npm ci --include=dev
↓
Vite build
↓
PostgreSQL
↓
Migrations
↓
Pint
↓
Larastan/PHPStan
↓
Pest
↓
ESLint
↓
Prettier
↓
vue-tsc
↓
Vitest
```

O build Vite precisa ocorrer antes do Pest porque testes que renderizam views dependem de:

```text
public/build/manifest.json
```

---

## 26. Deploy automático desejado

```text
feature/*
   ↓
Pull Request
   ↓
CI
   ↓
Merge na main
   ↓
GitHub Actions
   ↓ SSH
VPS
   ↓
git pull
   ↓
make prod-deploy
```

Recomendado:

- sem push direto na `main`;
- Pull Request obrigatório;
- CI verde obrigatório;
- deploy somente após merge na `main`.

---

## 27. Atualização manual enquanto o CD não estiver finalizado

```bash
ssh deploy@209.61.39.221
cd /opt/apps/gestao-clinicas

git fetch origin
git checkout main
git pull --ff-only origin main

make prod-build
make prod-up
make prod-migrate
make prod-optimize
make prod-status
```

---

## 28. Backup

Diretório reservado:

```text
/opt/backups
```

Ainda é necessário definir rotina automática para:

- PostgreSQL;
- MinIO;
- arquivos importantes;
- configuração do Traefik;
- cópia externa à VPS;
- retenção;
- teste de restore.

---

## 29. Segurança

1. Nunca versionar `.env`.
2. Nunca compartilhar chaves privadas.
3. Nunca abrir PostgreSQL ou Redis publicamente.
4. Não usar login SSH direto como root.
5. Usar Deploy Keys específicas.
6. Usar tokens Cloudflare de mínimo privilégio.
7. Rotacionar qualquer credencial exposta.
8. Manter Ubuntu e Docker atualizados.
9. Utilizar PR para produção.
10. Executar CI antes de deploy.

---

## 30. Comandos rápidos

Acesso:

```bash
ssh deploy@209.61.39.221
```

Root:

```bash
sudo -i
```

Projeto:

```bash
cd /opt/apps/gestao-clinicas
```

Status:

```bash
make prod-status
```

Logs:

```bash
make prod-logs
```

Atualizar:

```bash
git pull --ff-only origin main
```

Deploy:

```bash
make prod-deploy
```

Traefik:

```bash
cd /opt/traefik
docker compose ps
docker logs traefik --tail 100
```

Firewall:

```bash
sudo ufw status verbose
```

Docker:

```bash
docker ps
```

---

## 31. Pendências conhecidas

- Finalizar e validar o workflow de deploy automático após merge na `main`.
- Validar novamente o CI após ajuste da ordem do build Vite.
- Confirmar o ajuste de buffers FastCGI do Nginx em produção.
- Avaliar reativação do proxy Cloudflare após investigar o timeout de rota.
- Definir SMTP real.
- Definir política de backup/restore.
- Avaliar remoção do container Node permanente em produção usando build multi-stage.
- Evoluir deploy para imagens imutáveis/versionadas.
- Configurar monitoramento e observabilidade da VPS e containers.

---

## 32. Checklist para assumir a operação

```bash
ssh deploy@209.61.39.221
cd /opt/apps/gestao-clinicas
git status
git branch --show-current
make prod-status
docker ps
sudo ufw status verbose
docker logs traefik --tail 50
```

---

## 33. Regra de continuidade

Mudanças de código, Compose, Nginx, Makefile e workflows devem seguir:

```text
VS Code
→ branch
→ commit
→ push
→ Pull Request
→ CI
→ main
→ deploy
```

Segredos (`.env`, tokens, chaves e credenciais) ficam somente nos ambientes apropriados e nunca no Git.
