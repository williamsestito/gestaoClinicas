# Baseline de segurança

Esta é a base de segurança da fundação técnica. Políticas específicas de
cada módulo de negócio (ex.: LGPD para dados de pacientes) serão definidas
quando esses módulos forem implementados.

## Segredos

- Nenhuma senha real está versionada. `.env` nunca é commitado
  (`.gitignore`); apenas `.env.example`, com placeholders.
- Postgres, Redis e MinIO exigem senha obrigatoriamente — sem credenciais
  fixas no código ou nos arquivos versionados.
- `php artisan app:create-platform-admin` nunca aceita senha por argumento
  de linha de comando, nunca a registra em log, e pede confirmação
  explícita antes de promover um usuário existente.
- O seeder opcional `PlatformAdminSeeder` só roda se
  `SEED_PLATFORM_ADMIN=true` e todas as variáveis `PLATFORM_ADMIN_*`
  estiverem definidas; nunca usa senha padrão; é bloqueado em produção.

## Rede

- Todas as portas publicadas pelo Docker Compose são vinculadas a
  `127.0.0.1` — nada é exposto em `0.0.0.0`.
- Redis e PHP-FPM não publicam nenhuma porta para o host.
- MinIO não possui bucket público nem política de acesso anônimo.

## Aplicação

- CSRF habilitado via middleware padrão do Laravel.
- `SESSION_SECURE_COOKIE=false` apenas em ambiente local (HTTP);
  deve ser `true` em qualquer ambiente servido via HTTPS.
- `SESSION_SAME_SITE=lax`.
- `email_verified_at` é exigido (middleware `verified`) para acessar o
  dashboard e para acessar o painel Filament (`canAccessPanel`).
- O painel Filament (`/admin`) só é acessível a usuários com
  `is_active=true`, `is_platform_admin=true` e e-mail verificado.
- Erros detalhados (`APP_DEBUG=true`) somente em desenvolvimento.

## Nginx

- Bloqueia acesso a qualquer arquivo oculto (`.env`, `.git`, etc.).
- Impede listagem de diretórios (`autoindex off`).
- Não expõe a versão do servidor (`server_tokens off`).
- Uploads limitados a 50 MB.
- Nenhum arquivo PHP é executável dentro de `/storage`.
- Headers mínimos: `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`. Uma política de CSP restritiva será definida junto da
  interface e das integrações das próximas fases.

## Logs

- Nenhum comando ou script deste projeto registra senhas, chaves ou tokens
  em log — `app:doctor`, por exemplo, reporta apenas sucesso/falha por
  item, nunca o valor das credenciais.

## Regras para as próximas fases (documentadas, não implementadas)

- Nenhuma exclusão física de dados de negócio.
- Nenhum acesso a dados de outra organização/unidade (isolamento via
  `app/Support/Tenancy`, a ser implementado).
