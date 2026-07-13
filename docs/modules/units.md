# Unidades

## Modelo

`App\Models\Unit` — pertence a uma organização e a uma entidade legal.
`code` e `slug` únicos por organização. Uma organização tem exatamente uma
unidade matriz (`is_headquarters = true`), garantido por índice único
parcial (`units_one_headquarters_per_org`). Endereço via relação
polimórfica (`Address`) e horários via `UnitOpeningHour` (um ou mais
intervalos por dia da semana, dia sem intervalo é permitido).

## Horários de funcionamento

`day_of_week` 0 (domingo) a 6 (sábado), sempre em horário local da unidade
(não são datas — não fazem sentido como `datetime`). Sobreposição de
intervalos no mesmo dia é bloqueada em duas camadas:
`App\Support\OpeningHoursOverlapGuard` (Form Requests e Actions) e um
`CHECK` de banco garantindo `opens_at < closes_at` em cada intervalo.

## Criação e edição

- Unidade matriz: criada no onboarding
  (`App\Actions\Organization\OnboardOrganizationAction`, que delega a
  criação da unidade para `CreateUnitAction`).
- Novas unidades: `/settings/units/create` (Inertia), restrito ao
  proprietário da organização (`App\Policies\UnitPolicy`).
  `CreateUnitAction` roda em transação, gera um código único com retry em
  caso de colisão de concorrência (`units_organization_id_code_unique`) e
  concede ao criador um `UnitMembership` ativo (`is_manager = true`) — sem
  isso, quem cria uma unidade não conseguiria selecioná-la depois.
- Edição: `/settings/units/{unit}/edit` — nome, telefone, WhatsApp, e-mail
  e fuso horário (`UpdateUnitAction`). Endereço e horários são exibidos,
  não editáveis nesta tela ainda.
- Filament: `UnitResource`, mesmos campos editáveis, mais ações
  "Ativar"/"Inativar" (`ChangeUnitStatusAction`). Sem criação nem exclusão
  física pelo Filament — evita duplicar a regra de negócio já implementada
  no Inertia (endereço + horários).

## Ativação, exclusão lógica e unidade matriz

`Unit` usa `SoftDeletes` — "Excluir" (`DeleteUnitAction`) nunca é físico,
sempre marca `deleted_at` e é auditado; "Restaurar" (`RestoreUnitAction`)
reverte. A unidade matriz não pode ser excluída nem perder o status de
matriz sem antes designar outra (`SetHeadquartersUnitAction`, atômico: a
antiga matriz deixa de ser, a nova passa a ser, numa única transação —
evita violar o índice único parcial `units_one_headquarters_per_org`, que
também ignora unidades excluídas logicamente). Ativar/inativar usa
`ChangeUnitStatusAction`, reaproveitado pelo Inertia e pelo Filament.

## Isolamento

Rotas com `{unit}` no path passam por `EnsureUnitMembership`: a unidade da
URL precisa pertencer à organização ativa do usuário, estar com
`status = active` e o usuário precisa ter `UnitMembership` ativo para essa
unidade específica (não basta pertencer à mesma organização) — qualquer
divergência resulta em 404/403.
