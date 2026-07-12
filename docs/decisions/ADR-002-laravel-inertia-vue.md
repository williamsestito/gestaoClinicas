# ADR-002: Laravel + Inertia.js + Vue 3

## Status

Aceito.

## Contexto

O produto precisa de uma interface rica e reativa (agenda, prontuário,
dashboards) mantida por uma equipe que já trabalha com Laravel no backend,
sem o custo operacional de manter uma API REST/GraphQL separada e um SPA
totalmente desacoplado.

## Decisão

Usar **Inertia.js 3** como ponte entre Laravel e **Vue 3 (Composition API)
com TypeScript**, em vez de uma API JSON consumida por um SPA independente.
UI com Tailwind CSS + shadcn-vue.

## Consequências

- Roteamento e controllers permanecem no Laravel; não há duplicação de
  camada de API para telas internas.
- Páginas Vue vivem em `resources/js/pages`, renderizadas via
  `Inertia::render()`.
- Autenticação via Fortify funciona nativamente com Inertia (sem tokens de
  API para o frontend web).
- Caso um app mobile ou uma API pública sejam necessários no futuro, uma
  camada de API dedicada terá que ser adicionada — não existe nesta fase.
