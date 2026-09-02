# Serviços

## Modelo

`App\Models\Service` — pertence a uma organização. `code` único por
organização (ignorando excluídos). Duração e buffers em minutos, preço em
centavos (`default_price_cents`, nunca ponto flutuante). `is_public`
sinaliza intenção de exibição pública futura — não publica nada sozinho.
`unit_availability_scope` (`App\Enums\ServiceAvailabilityScope`: todas as
unidades / unidades selecionadas / nenhuma) resolve, via
`Service::availableUnitIds()`, em quais unidades o serviço está disponível
— dimensão independente do status ativo/inativo.

Sem relação com `App\Models\SiteService` (vitrine pública, cadastro
próprio de marketing — ver [public-integration.md](public-integration.md)).

## Vínculos

- `specialtyLinks()`/`unitLinks()` — sincronizados atomicamente por
  `App\Support\ServiceLinkSynchronizer` a partir da lista final desejada de
  ids. Cada id é revalidado contra `service->organization_id` **dentro do
  Synchronizer**, não apenas no Form Request do Inertia — necessário porque
  o formulário de edição do Filament (`ServiceForm`) chama a mesma Action
  sem passar pelo Form Request; sem essa revalidação, um payload adulterado
  poderia vincular uma especialidade/unidade de outra organização mesmo com
  o Select do Filament restringindo visualmente as opções.
- `professionalLinks()` — profissionais que executam o serviço
  (`ProfessionalService`, com possível sobrescrita de duração/preço por
  unidade via `ProfessionalServiceUnit`).

## Criação, edição, ativação e exclusão

`/settings/services`, restrito por `PermissionKey::ServicesManage`.
`DeleteServiceAction` bloqueia exclusão com profissionais ativamente
vinculados; vínculos de especialidade/unidade não bloqueiam (são apenas
preservados). `RestoreServiceAction` restaura sempre inativo e não recria
vínculos removidos antes da exclusão (precisam ser refeitos
explicitamente).

## Filtros da listagem

`App\Queries\ServiceListQuery` resolve, sem N+1, especialidade(s),
unidade(s) disponíveis, contagem de profissionais ativos e disponibilidade
operacional (`has_available_unit`, calculado a partir do escopo já
eager-carregado). Filtros: status, especialidade, unidade, com/sem
profissionais vinculados, exibição pública, disponibilidade operacional.

## Filament

`App\Filament\Resources\Services\ServiceResource` — mesmo padrão de
`SpecialtyResource`. O formulário de edição inclui seletores de
especialidade/unidade (`CheckboxList`) com opções restritas à mesma
organização do registro — reforçados pela revalidação no
`ServiceLinkSynchronizer` descrita acima. Preço convertido reais↔centavos
na página `EditService`, nunca no formulário.
