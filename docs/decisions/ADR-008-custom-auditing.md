# ADR-008: Auditoria própria (sem pacote externo)

## Status

Aceito.

## Contexto

A Fase 1 precisa registrar quem alterou o quê (organizações, unidades,
entidades legais, troca de contexto), com dados sensíveis (CPF/CNPJ)
mascarados. Pacotes de auditoria genéricos (ex.: `owen-it/laravel-auditing`)
capturam mudanças via observers/eventos de forma automática, o que é
conveniente mas dificulta controlar precisamente o que é sanitizado antes
de gravar.

## Decisão

Implementar auditoria própria: tabela `audit_logs` +
`App\Support\Auditing\AuditLogger`, chamado **explicitamente** dentro de
cada Action que precisa gerar um registro.

## Consequências

- Controle total sobre sanitização (mascaramento de documento, remoção de
  campos sensíveis) antes de qualquer gravação.
- Cada chamada de auditoria é visível no código da Action — fácil de
  testar e de auditar o próprio auditor.
- Exige disciplina: uma Action nova que devesse ser auditada e não chama
  `AuditLogger::log()` não é pega automaticamente (ao contrário de um
  observer genérico). Mitigado por testes dedicados
  (`tests/Feature/Organization/AuditLoggingTest.php`).
- Se o volume de eventos a auditar crescer muito nas próximas fases, a
  decisão pode ser revisitada em favor de um observer central — não é o
  caso ainda nesta fase.
