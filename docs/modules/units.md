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
  (`App\Actions\Organization\CreateUnitAction`).
- Novas unidades: `/settings/units/create` (Inertia), restrito ao
  proprietário da organização (`App\Policies\UnitPolicy`).
- Edição: `/settings/units/{unit}/edit` — nome, telefone, WhatsApp, e-mail
  e fuso horário (`UpdateUnitAction`). Endereço e horários são exibidos,
  não editáveis nesta tela ainda.
- Filament: `UnitResource`, mesmos campos editáveis, mais ações
  "Ativar"/"Inativar" (`ChangeUnitStatusAction`). Sem criação nem exclusão
  pelo Filament — evita duplicar a regra de negócio já implementada no
  Inertia (endereço + horários).

## Isolamento

Rotas com `{unit}` no path passam por `EnsureUnitMembership`: a unidade da
URL precisa pertencer à organização ativa do usuário, ou a resposta é 404.
