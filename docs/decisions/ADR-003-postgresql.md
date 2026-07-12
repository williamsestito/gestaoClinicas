# ADR-003: PostgreSQL

## Status

Aceito.

## Contexto

O sistema lidará com dados relacionais complexos (agenda, prontuário,
financeiro) que se beneficiam de tipos avançados, constraints fortes e
recursos como JSONB, full-text search nativo e particionamento.

## Decisão

Usar **PostgreSQL 17** como banco de dados principal, com um banco de
dados separado (`gestao_clinicas_test`) para testes automatizados —
nunca SQLite, mesmo em testes.

## Consequências

- Ambiente de desenvolvimento e testes precisa de um Postgres real
  (fornecido via Docker Compose), em vez do atalho de SQLite em memória.
- Testes automatizados refletem o comportamento real do banco de produção
  (tipos, constraints, comportamento de transações).
- Recursos específicos do PostgreSQL (JSONB, full-text search, etc.) podem
  ser usados livremente nas próximas fases, sem preocupação de
  portabilidade para outro SGBD.
