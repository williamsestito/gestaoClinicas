# Jornada e disponibilidade

## Modelagem — sem tabela de slots

A disponibilidade nunca é persistida como slots pré-calculados. É sempre
computada sob demanda:

```text
Profissional ativo
+ vínculo profissional-unidade ativo/vigente
+ unidade ativa
+ jornada regular ativa/vigente
− ausências/bloqueios ativos que se aplicam ao dia/unidade
= disponibilidade efetiva do dia
```

`App\Services\Availability\ProfessionalAvailabilityResolver::resolve()` é o
único ponto que produz esse resultado, retornando
`App\Data\Availability\DailyAvailabilityData` (intervalos + avisos, nunca
um Model).

## Jornada regular — hora civil local, nunca UTC

`App\Models\ProfessionalWorkingHour` — vinculada a `ProfessionalUnit` (não
diretamente ao profissional), com `weekday` (0–6, mesma convenção de
`UnitOpeningHour`), `starts_at`/`ends_at` (hora local, sem timezone — é uma
regra recorrente, não um evento datado) e vigência opcional
(`effective_from`/`effective_until`). Sobreposição (mesma unidade e entre
unidades do mesmo profissional) é bloqueada por
`App\Support\Availability\WorkingHourOverlapGuard` dentro de
`DB::transaction()` com `lockForUpdate()`; contenção no horário de
funcionamento da unidade por `WorkingHourOpeningHoursGuard` — se a unidade
mudar de horário depois, o resolver emite um aviso em vez de apagar dados.
Cópia entre dias (`CopyProfessionalWorkingHoursAction`) é atômica: valida
todos os dias-alvo antes de persistir qualquer um.

## Ausências e bloqueios — instante UTC

`App\Models\ProfessionalTimeBlock` — `type` (férias, folga, ausência,
bloqueio administrativo, evento externo, indisponibilidade parcial) e
`scope` explícito (`all_units` ou `specific_unit`, nunca nulo implícito,
reforçado por CHECK de banco). `starts_at`/`ends_at` são timestamps UTC
reais — ao contrário da jornada, representam um evento datado.
`App\Support\Availability\LocalTimeConverter` converte data/hora informada
no fuso da unidade (ou `Professional::referenceTimezone()` para bloqueios
"todas as unidades") para UTC antes de gravar.
`App\Support\Availability\TimeBlockOverlapGuard` bloqueia sobreposição
considerando interseção de escopo: um bloqueio "todas as unidades" conflita
com qualquer outro bloqueio do profissional.

## Por que guards de aplicação, não `EXCLUDE` do Postgres

Sobreposição poderia ser garantida por uma constraint `EXCLUDE` com
`btree_gist`, mas essa extensão não está instalada nem usada em nenhum
outro lugar do projeto — instalá-la só para isso seria alterar
dependências sem necessidade comprovada. Optou-se pelo padrão já
estabelecido no projeto (`OpeningHoursOverlapGuard`): guard de aplicação +
transação + `lockForUpdate()`.

## Permissão com escopo por unidade — a primeira do projeto

`ProfessionalPolicy::manageAvailability()`/`manageTimeBlocks()` aceitam
acesso amplo (papel com `ProfessionalAvailabilityManage`/
`ProfessionalTimeBlocksManage` org-wide) **ou** acesso restrito: o usuário
tem essa permissão E possui `UnitMembership.is_manager = true` para a
unidade específica do vínculo/bloqueio em questão. Ver
[permissions.md](../architecture/permissions.md) para a nota completa —
nenhuma outra permissão do catálogo tem esse escopo hoje.

## Interface

Aba "Disponibilidade" (jornada semanal por unidade) e "Ausências" na ficha
do profissional. Resumo operacional mostra se há jornada configurada e a
próxima ausência agendada. Filtros da listagem de profissionais incluem
com/sem jornada e com/sem ausência em andamento.
