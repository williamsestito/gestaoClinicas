# ADR-001: Monólito modular

## Status

Aceito.

## Contexto

O sistema atenderá múltiplos tipos de estabelecimento (clínicas de
estética, odontologia, massagens, terapias, consultórios) e precisará
crescer com módulos de negócio (organizações, unidades, profissionais,
pacientes, agenda, prontuário, financeiro, produtos, estoque, vendas).

## Decisão

Construir um **monólito modular** em Laravel, com separação lógica em
`app/` (Actions, Services, Policies, etc.) e `resources/js/`, em vez de
microsserviços ou uma API separada do frontend.

## Consequências

- Deploy único, mais simples de operar nesta fase inicial.
- Reuso direto de autenticação, autorização e infraestrutura entre módulos.
- Exige disciplina para manter os módulos desacoplados internamente
  (Actions/Services/Policies bem definidos) para não acumular acoplamento
  implícito.
- Migração futura para serviços separados, se necessária, fica mais cara
  do que já nascer distribuído — decisão consciente, priorizando
  velocidade de entrega e simplicidade operacional nesta fase.
