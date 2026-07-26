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
- **Segredo dedicado de passkeys** (`PASSKEYS_USER_HANDLE_SECRET`,
  `config/fortify.php` → `passkeys.user_handle_secret`): usado pelo pacote
  `laravel/passkeys` para derivar o "user handle" do WebAuthn. Fora de
  produção, cai em `APP_KEY` por conveniência (dev/teste). **Em produção,
  não há fallback** — se a variável não estiver definida, o valor é `null`
  e qualquer fluxo de passkey falha de forma explícita (exceção do
  Laravel ao ler a config), sem afetar login por senha ou 2FA. Gere um
  valor com:
  ```bash
  php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
  ```
  Nunca versione o valor gerado nem o registre em log. Os testes usam um
  valor fixo e dedicado definido em `phpunit.xml`, nunca o `APP_KEY` real.

## Rede

- Todas as portas publicadas pelo Docker Compose são vinculadas a
  `127.0.0.1` — nada é exposto em `0.0.0.0`.
- Redis e PHP-FPM não publicam nenhuma porta para o host.
- MinIO não possui bucket público nem política de acesso anônimo.

## Aplicação

- CSRF habilitado via middleware padrão do Laravel; nenhuma rota web
  possui exceção (`bootstrap/app.php` não define `validateCsrfTokens`).
- `SESSION_SECURE_COOKIE=false` apenas em ambiente local (HTTP);
  deve ser `true` em qualquer ambiente servido via HTTPS.
- `SESSION_SAME_SITE=lax`.
- `email_verified_at` é exigido (middleware `verified`) para acessar o
  dashboard e para acessar o painel Filament (`canAccessPanel`).
- O painel Filament (`/admin`) só é acessível a usuários com
  `is_active=true`, `is_platform_admin=true` e e-mail verificado.
- Erros detalhados (`APP_DEBUG=true`) somente em desenvolvimento.
- **Proxies confiáveis**: `TRUSTED_PROXIES` (vazio por padrão — nenhum
  proxy confiável, comportamento inalterado para quem não define a
  variável). Em produção atrás de um load balancer/edge, defina `*`
  (comum atrás de um edge gerenciado) ou uma lista de IPs/CIDRs — sem
  isso, `Request::ip()` (usado em auditoria e rate limit por IP) e a
  URL gerada podem refletir o proxy em vez do cliente/host reais.
- **CORS**: não configurado — este projeto é Inertia-only (nenhuma rota
  de API cross-origin existe). Decisão intencional, não uma lacuna;
  reavaliar apenas se uma API pública for introduzida em fase futura.
- **Headers de segurança** aplicados tanto pelo nginx (ver seção
  abaixo) quanto pela aplicação (`App\Http\Middleware\SecurityHeaders`,
  no grupo `web`), para não depender de um servidor web específico em
  produção (ex.: Laravel Cloud pode não usar este nginx):
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
  - `Strict-Transport-Security` — **somente em produção e apenas quando a
    requisição já chega via HTTPS** (`$request->secure()`); nunca em
    HTTP local. Sem `includeSubDomains` (decisão operacional adiada).
  - `Content-Security-Policy-Report-Only` — **somente em produção**,
    nunca bloqueia a página (apenas registra violações no console do
    navegador). Política atual (conservadora, `'self'` + estilos
    inline para Tailwind/shadcn):
    ```
    default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';
    img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';
    frame-ancestors 'self'; base-uri 'self'; form-action 'self'
    ```
    Antes de promover para uma CSP *enforced* (bloqueante), valide contra
    qualquer script de analytics/GTM/mapa que venha a ser adicionado —
    nenhum existe hoje na landing page.
- **Rate limiting** mapeado (nome/rota → limite):
  - `login` (Fortify) — 5/min por e-mail+IP
  - `two-factor` (Fortify) — 5/min por sessão do desafio
  - `passkeys` (Fortify) — 10/min por credencial/sessão+IP
  - `direct-password-reset` — 20/min por IP (rota local/testing apenas)
  - `settings/invitations/{invitation}/resend` — 6/min
  - `invitations/{token}` (aceite público) — 6/min
  - `settings/password` (troca de senha) — 6/min
  - `context/organization`, `context/unit` (troca de contexto ativo) —
    20/min (adicionado nesta fase)
  - `agendamento` (solicitação pública) — 5/min
  - `cep/{postalCode}` (lookup de CEP) — 30/min

## Nginx

- Bloqueia acesso a qualquer arquivo oculto (`.env`, `.git`, etc.).
- Impede listagem de diretórios (`autoindex off`).
- Não expõe a versão do servidor (`server_tokens off`).
- Uploads limitados a 50 MB.
- Nenhum arquivo PHP é executável dentro de `/storage`.
- Headers mínimos: `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy` (duplicados pela aplicação, ver seção "Aplicação" —
  garante o mesmo baseline independente do servidor web usado no deploy
  real). CSP em modo `Report-Only` definida na Etapa 0.8; uma política
  restritiva *enforced* fica para quando houver integrações externas
  (analytics, mapas) a validar.

## Logs

- Nenhum comando ou script deste projeto registra senhas, chaves ou tokens
  em log — `app:doctor`, por exemplo, reporta apenas sucesso/falha por
  item, nunca o valor das credenciais.
- Nenhuma chamada `Log::*`/`dd()`/`dump()` em `app/` expõe payload
  sensível (revisado na Etapa 0.8) — os únicos usos de `Log::warning`
  registram apenas a classe da exceção de provedores de CEP, nunca dados
  de requisição.

## Auditoria

- `App\Support\Auditing\AuditLogger` é chamado explicitamente pelas
  Actions (nunca via observer/evento mágico) — ver
  [auditing.md](auditing.md).
- Sanitização por lista de chaves (bloqueio total: senha, tokens,
  segredos, códigos de recuperação, segredo de 2FA, credencial de
  passkey; mascaramento: CPF/CNPJ), recursiva em qualquer profundidade —
  aplica-se tanto a entidades legais quanto ao CPF do próprio usuário
  (`users.cpf`, coletado no perfil desde a Etapa 0.9), já que a
  sanitização é por nome de chave, não por tabela.

## Upload de arquivos (perfil, site público)

- Fotos de perfil, logo/banner/favicon do site e imagens da galeria usam
  `App\Rules\ValidImageContentRule` (valida o conteúdo real do arquivo,
  não apenas a extensão) e um limite de tamanho explícito por tipo — nunca
  aceitam upload de PHP disfarçado de imagem.
- Troca de arquivo é sempre feita via `App\Support\Site\SafeFileReplacer`
  (stage → commit/rollback): o arquivo antigo só é removido depois que a
  escrita no banco é confirmada, nunca antes — evita perder o arquivo
  anterior se a request falhar no meio do caminho.
- Favicon é convertido para múltiplos tamanhos (16/32/48/180/192px) via GD
  no servidor (`App\Support\Site\FaviconGenerator`) — o usuário nunca
  precisa fornecer um arquivo já nos formatos/tamanhos exigidos por
  navegadores/dispositivos.
- `AuditLog` é imutável: `update()`/`delete()` lançam exceção no próprio
  model, a policy nega `create`/`update`/`delete` incondicionalmente, e
  não existe rota de escrita — apenas `GET settings/audit` (somente
  leitura, escopado por organização, exige `audit.view`/owner/platform
  admin).

## Isolamento entre organizações (revisão transversal — Etapa 0.8)

- Toda tabela de negócio é escopada manualmente por `organization_id`
  (não há global scope) — reforçado por: policies que derivam o
  `organization_id` sempre do modelo resolvido (nunca do contexto ativo
  da sessão), e middlewares `tenant.*-membership` que revalidam
  `{organization}`/`{unit}`/`{legalEntity}` do path contra o contexto
  ativo antes de a Policy rodar.
- **`site_settings` é global por decisão arquitetural (ADR-010)** — não é
  uma falha de isolamento, é o comportamento esperado (instalação
  single-tenant por design nesta fase).
- Dois gaps de isolamento corrigidos nesta fase (ver `CHANGELOG`/commit
  `fix(fase-0.8)`): atualização de status/observação de solicitação de
  agendamento sem checar a organização do registro (IDOR), e associação
  de unidade de outra organização a um vínculo via `unit_ids`/
  `primary_unit_id` sem escopo.

## Checklist de deploy seguro (produção)

Variáveis obrigatórias/recomendadas, sem valores reais aqui:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` com esquema `https://`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_SAME_SITE=lax` (mantém, salvo necessidade de integração
  cross-site futura)
- `PASSKEYS_USER_HANDLE_SECRET` definido com valor dedicado (gerado uma
  única vez, nunca reaproveitado de `APP_KEY`)
- `TRUSTED_PROXIES` definido (`*` ou lista de IPs) se houver load
  balancer/edge na frente da aplicação
- `LOG_LEVEL` em `info`/`warning` (não `debug`) e canal `daily` com
  retenção definida (`LOG_DAILY_DAYS`, recomendado 14–30 dias conforme
  necessidade de diagnóstico vs. volume)
- Filas (`QUEUE_CONNECTION`) e cache (`CACHE_STORE`) em Redis, não
  `sync`/`array`, para não bloquear requests em notificações/e-mails
- `php artisan config:cache`, `route:cache`, `view:cache` no deploy
- Nunca usar `migrate:fresh` em produção

Este checklist não altera o ambiente real de produção — é apenas
documentação para quem for operar o deploy.

## Regras para as próximas fases (documentadas, não implementadas)

- Nenhuma exclusão física de dados de negócio.
- Nenhum acesso a dados de outra organização/unidade (isolamento via
  `app/Support/Tenancy`, a ser implementado).
- Criptografia de campos clínicos, política de retenção/anonimização e
  conformidade LGPD ficam para quando os módulos de prontuário/paciente
  existirem — fora do escopo desta fundação técnica.
