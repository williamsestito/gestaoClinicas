# ADR-009: Endereços via relação polimórfica

## Status

Aceito.

## Contexto

Entidade legal e unidade precisam de endereço. Pacientes (fase futura)
também vão precisar. Duplicar as mesmas 7 colunas de endereço em cada
tabela geraria repetição e inconsistência de validação.

## Decisão

Uma tabela `addresses` única, associada via relação polimórfica
(`addressable_type`/`addressable_id`), com **morph map explícito**
(`App\Providers\AppServiceProvider::configureMorphMap()`) — nunca o nome
completo da classe PHP é gravado no banco.

## Consequências

- Uma única definição de validação/formulário de endereço
  (`App\Data\Organization\AddressData`, componente `AddressFields.vue`),
  reutilizada no onboarding e na criação de unidades.
- `organization_id` é duplicado na própria tabela `addresses` (além do
  vínculo indireto via `addressable`), para permitir escopar/indexar por
  organização sem precisar fazer join com a tabela polimórfica alvo.
- Cada entidade que possui endereço só pode ter **um** (`morphOne`) nesta
  fase — suficiente para entidade legal e unidade; se algum tipo precisar
  de múltiplos endereços no futuro, isso exigirá revisar essa relação.
