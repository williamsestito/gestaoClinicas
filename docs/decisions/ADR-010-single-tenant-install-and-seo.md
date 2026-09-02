# ADR-010: Instalação única por clínica (front público) + SEO sem Inertia SSR

## Status

Aceito.

## Contexto

Cada cliente da plataforma passará a rodar sua própria instalação
(domínio, app, banco, variáveis de ambiente, storage e usuários
independentes) em vez de compartilhar uma única instalação multi-cliente.
Isso levanta duas perguntas de arquitetura:

1. O que fazer com o modelo de organizações/unidades (`organization_id`/
   `unit_id`, ver [docs/architecture/tenancy.md](../architecture/tenancy.md))
   já construído para multiempresa — removê-lo, já que só existirá uma
   clínica por instalação?
2. Como entregar metadados de SEO (título, canonical, Open Graph,
   JSON-LD) no HTML inicial sem depender só de JavaScript client-side,
   já que o projeto usa Inertia sem SSR?

## Decisão

### 1. Manter o modelo de organizações/unidades como está

Não remover `organization_id`/`unit_id`, `TenantContext` nem os
middlewares de contexto. Uma instalação single-tenant continua podendo
ter mais de uma **unidade** (matriz + filiais) dentro da mesma
organização — o conceito que muda é apenas "quantas organizações-cliente
diferentes uma instalação atende", não "quantas unidades uma clínica
pode ter".

O front público (`PublicSiteController::home()`) carrega **a**
organização da instalação com `Organization::query()->first()` — nunca
uma lista para escolher, nunca "a primeira dentre várias". Não existe
seletor público de clínica, resolução de tenant por subdomínio, nem
lógica de troca de cliente nessa rota. Se ainda não houver nenhuma
organização cadastrada (onboarding não concluído), a página exibe um
estado "Ambiente em configuração" — nunca um erro técnico ou página
quebrada — mantendo os links de login/registro para que um
administrador consiga concluir o onboarding.

A lógica de auto-seleção que já existia em `ResolveOrganizationContext`
(seleciona automaticamente quando o usuário tem só um vínculo ativo) já
produz, na prática, uma experiência single-tenant dentro de uma
instalação genuinely single-clinic — sem precisar de nenhuma mudança de
código nessa camada.

### 2. Não introduzir Inertia SSR nesta fase

Inertia, no adapter Laravel, já renderiza `resources/views/app.blade.php`
no servidor a cada carregamento de página completo e compartilha o array
`$page` (incluindo os props Inertia, como `seo`) com essa view. Isso
permite ler `$page['props']['seo']` diretamente no Blade e renderizar
`<title>`, `<meta name="description">`, `<link rel="canonical">`, as
tags Open Graph/Twitter e o `<script type="application/ld+json">` no
HTML inicial — sem executar nenhum JavaScript — satisfazendo o requisito
de que esses metadados não dependam só de client-side, sem a
complexidade adicional (build, deploy, testes, manutenção) de um
servidor Node de SSR.

O componente `<Head>` do Inertia/Vue reconcilia com as tags já
renderizadas pelo servidor (casa por tipo de tag) em vez de duplicá-las;
por isso `resources/views/app.blade.php` usa `<x-inertia::head />`
(auto-fechada, sem título fixo) para não gerar `<title>` duplicado.

SSR real só deve ser reconsiderado se surgir uma necessidade concreta que
esse mecanismo não cubra (ex.: crawlers que não executam JS mas também
não leem a resposta HTML completa do primeiro request — cenário não
identificado até o momento).

## Consequências

- Nenhuma refatoração destrutiva do modelo de tenancy: baixo risco, sem
  perda de capacidade multiempresa caso ela volte a ser necessária no
  futuro.
- O front público continua funcionando localmente em
  `http://localhost:8080/` sem qualquer dependência de domínio, protocolo
  ou nome de clínica hard-coded — tudo resolvido via `APP_URL` e
  `App\Support\Seo\CanonicalUrlResolver`.
- Metadados de SEO chegam no HTML inicial sem SSR, mas o mecanismo é
  específico da rota `home()` — cada nova página pública que precisar de
  metadados terá que popular o mesmo prop `seo` e reaproveitar
  `SeoMetaBuilder`.
- Ver [docs/architecture/seo.md](../architecture/seo.md) para o
  detalhamento de canonical, robots.txt, sitemap, JSON-LD e todo o
  restante da estratégia de SEO/marketing, e a lista explícita do que
  ficou pendente para fases futuras.
