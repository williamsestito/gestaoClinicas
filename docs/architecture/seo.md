# Domínio próprio, SEO e acessibilidade (página pública inicial)

Este documento cobre a "fundação" de SEO técnico e acessibilidade
implementada para a página pública inicial (`/`): modelo de dados,
resolução de URL canônica, robots.txt/sitemap dinâmicos, metadados
(title/description/Open Graph/Twitter) e JSON-LD, entregues sem Inertia
SSR. Ver [ADR-010](../decisions/ADR-010-single-tenant-install-and-seo.md)
para a decisão de arquitetura por trás disso e
[docs/architecture/tenancy.md](tenancy.md) para o modelo de
organizações/unidades.

## Domínio oficial e URL canônica

`SiteSetting.official_domain` é um campo opcional, administrável em
**Site e SEO** (`/admin/manage-site-content`), validado por
`App\Rules\OfficialDomainRule` (aceita apenas um hostname puro — sem
esquema, caminho, query string, fragmento ou caracteres perigosos).

`App\Support\Seo\CanonicalUrlResolver::resolve()` é o **único** ponto do
código que decide a URL base a usar:

- Em produção, com `official_domain` configurado: `https://{domínio}`.
- Em qualquer outro caso (ambiente local/staging, ou produção sem domínio
  configurado ainda): usa `config('app.url')` — nunca um domínio, protocolo
  ou porta hard-coded.

Nenhum outro arquivo concatena domínio manualmente; `LocalBusinessJsonLd`
e `SeoMetaBuilder` (canonical, Open Graph, `sameAs`, `hasMap`, sitemap,
robots.txt) usam sempre esse serviço.

## Metadados e JSON-LD sem SSR

`App\Http\Controllers\PublicSiteController::home()` monta o prop `seo`
via `App\Support\Seo\SeoMetaBuilder::forHome()` e o envia como prop
Inertia normal. `resources/views/app.blade.php` lê
`$page['props']['seo']` e renderiza no HTML inicial (sem JS):
`<title>`, `<meta name="robots">`, `<meta name="description">`,
`<link rel="canonical">`, Open Graph, Twitter Card e
`<script type="application/ld+json">`. Ver ADR-010 para o porquê de não
introduzir Inertia SSR para isso.

`App\Support\Seo\LocalBusinessJsonLd::build()` monta um JSON-LD
`LocalBusiness` (tipo configurável entre `LocalBusiness`,
`MedicalClinic`, `Dentist`, `Physician`, `HealthAndBeautyBusiness` ou
`ProfessionalService` via `SiteSetting.schema_type`) **só com dados reais
configurados**: nunca inventa `geo` (exige latitude e longitude
configuradas), `sameAs` (só URLs configuradas em
`google_business_profile_url`/`google_reviews_url`) ou qualquer
avaliação/nota/prêmio. Retorna `null` quando não há telefone nem endereço
da unidade matriz, para nunca publicar um `LocalBusiness` quase vazio.

## robots.txt e sitemap.xml dinâmicos

`App\Http\Controllers\SeoController` serve `/robots.txt` e
`/sitemap.xml` dinamicamente (rotas em `routes/public-site.php`, nunca
arquivos estáticos — havia um `public/robots.txt` estático removido
nesta fase).

- **Fora de produção**: `robots.txt` sempre bloqueia tudo
  (`Disallow: /`), **independentemente** da política de indexação
  configurada — nunca indexa ambiente local/staging por engano.
  `sitemap.xml` retorna 404.
- **Em produção**: só permite indexação quando
  `SiteSetting.indexing_policy = Index` (opt-in explícito, nunca
  automático). Quando permitido, `robots.txt` libera as rotas públicas e
  bloqueia explicitamente `/login`, `/register`, `/dashboard`,
  `/settings`, `/admin` e demais rotas internas de autenticação — mas
  isso é só sinalização para crawlers bem-comportados, **não** um
  mecanismo de segurança; essas rotas continuam protegidas por
  autenticação/autorização reais.
- `sitemap.xml` inclui só URLs reais e indexáveis (hoje, apenas a home),
  com `lastmod` real (baseado em `SiteSetting.updated_at`, nunca
  artificialmente atualizado a cada request).

O `meta name="robots"` por página segue a mesma regra: força
`noindex, nofollow` fora de produção independentemente da configuração,
e usa a política configurada apenas em produção.

## Acessibilidade estrutural (esta fase)

`resources/js/pages/Welcome.vue` implementa a base estrutural de
WCAG 2.2 AA: link de pular para o conteúdo (primeiro elemento focável da
página), landmarks únicos (`header`/`main`/`footer`, um único `<h1>`),
`alt` significativo na imagem de destaque (reaproveitando
`og_image_alt` configurado, com fallback genérico), `fetchpriority="high"`
na imagem do hero, e um estado "Ambiente em configuração" acessível
(nunca um erro técnico) quando ainda não há organização cadastrada.

**Conformidade declarada nesta fase**: a implementação atende aos
critérios automatizados configurados e foi validada manualmente conforme
o checklist registrado. Não se declara "100% acessível" — essa afirmação
exigiria a bateria completa listada em "Pendências" abaixo, ainda não
executada.

## Pendências explicitamente adiadas para fases futuras

Por decisão de escopo (fundação primeiro), ficou fora desta fase:

- Banner de consentimento de cookies (necessários/analytics/publicidade/
  personalização) e Google Consent Mode.
- Integração com Google Analytics 4, Google Tag Manager e Google Ads
  (IDs, eventos, conversões) — cada instalação terá configuração própria
  quando implementado, nunca um ID global compartilhado.
- Captura e persistência de parâmetros de campanha (UTM, `gclid`,
  `gbraid`, `wbraid`) e modelo de atribuição first/last-touch.
- Páginas públicas `/politica-de-privacidade`, `/termos-de-uso` e
  `/preferencias-de-privacidade` com texto legal definitivo (requer
  validação por responsável competente).
- Seção de perguntas frequentes (FAQ) e demais páginas públicas futuras
  (`/servicos`, `/profissionais`, `/unidades`, `/sobre`, `/contato`).
- Testes automatizados de acessibilidade com axe-core (ou equivalente
  compatível com Vue) integrados ao Vitest.
- Auditorias Lighthouse (desktop e mobile) documentadas para
  Performance/Acessibilidade/Boas práticas/SEO.
- Checklist manual completo de acessibilidade: navegação só por teclado,
  leitor de tela (VoiceOver/NVDA), zoom 200%, contraste, `prefers-reduced-motion`,
  orientação de tela, mensagens de erro específicas em formulários.

## Testes automatizados desta fase

- `tests/Unit/Support/Seo/OfficialDomainRuleTest.php`
- `tests/Feature/Support/Seo/CanonicalUrlResolverTest.php`
- `tests/Feature/Support/Seo/LocalBusinessJsonLdTest.php`
- `tests/Feature/SeoRoutesTest.php`
- `tests/Feature/PublicSiteTest.php`
- `tests/Feature/Filament/ManageSiteContentTest.php`
- `resources/js/pages/Welcome.spec.ts`
