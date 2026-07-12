# ADR-007: Contexto ativo (organização/unidade) na sessão

## Status

Aceito.

## Contexto

Um usuário pode pertencer a mais de uma organização e, dentro dela, ter
acesso a mais de uma unidade. A aplicação precisa saber "onde" o usuário
está operando a cada requisição, sem depender de parâmetros manipuláveis
vindos do frontend.

## Decisão

Guardar `active_organization_id`/`active_unit_id` na **sessão do
servidor** (Redis), revalidados a cada requisição pelos middlewares
`ResolveOrganizationContext`/`ResolveUnitContext` contra os vínculos reais
do usuário — nunca confiando apenas no valor salvo. Seleção automática
quando há apenas uma opção; caso contrário, o usuário escolhe em
`/context/organization` ou `/context/unit`.

## Consequências

- Simples de implementar e testar; não exige nenhum estado extra no
  frontend (Inertia recebe o contexto resolvido via props/página).
- Troca de contexto é auditada (`AuditAction::OrganizationContextSwitched`/
  `UnitContextSwitched`).
- Um ID de sessão inválido/obsoleto (ex.: usuário perdeu acesso) é limpo
  automaticamente na próxima requisição, sem erro visível ao usuário.
- Efeito colateral aceito: abrir a aplicação em duas abas com contextos
  diferentes não é suportado nesta fase (a sessão é única por usuário) —
  revisar se isso virar um requisito real no futuro.
