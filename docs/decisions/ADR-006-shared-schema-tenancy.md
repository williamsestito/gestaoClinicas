# ADR-006: Multiempresa por banco e schema compartilhados

## Status

Aceito.

## Contexto

O produto precisa isolar dados entre organizações (e, dentro delas, entre
unidades) desde a Fase 1. As alternativas comuns são: banco por tenant,
schema por tenant, ou banco/schema únicos com uma coluna discriminadora.

## Decisão

Usar um **banco e schema únicos**, com `organization_id` (e `unit_id`
quando aplicável) em toda tabela de negócio. Nenhum pacote externo de
multi-tenancy foi instalado. Isolamento garantido por convenção de query +
autorização (Policies, middlewares de contexto), nunca por Global Scope
implícito.

## Consequências

- Operação e deploy mais simples nesta fase (um único banco para migrar,
  monitorar e fazer backup).
- Exige disciplina: toda query de domínio precisa filtrar por organização
  explicitamente — não há uma rede de segurança automática do banco.
- `EnsureOrganizationMembership`/`EnsureUnitMembership` cobrem o caso mais
  perigoso (IDOR via URL), mas novas queries em código futuro precisam
  continuar filtrando por `organization_id` conscientemente.
- Migração futura para isolamento mais forte (schema ou banco por tenant),
  se o volume/compliance exigir, fica mais cara do que nascer já isolada
  — decisão consciente, priorizando velocidade nesta fase.
