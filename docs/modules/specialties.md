# Especialidades

## Modelo

`App\Models\Specialty` — pertence a uma organização (`organization_id`,
ULID). `name`/`code` únicos por organização, ignorando registros excluídos
logicamente (índice único parcial). `display_order` controla a ordem de
exibição nas telas administrativas.

Sem relação com o campo livre `specialty` de `App\Models\SiteProfessional`
(vitrine pública, texto solto digitado pelo administrador do site — ver
[public-integration.md](public-integration.md)).

## Vínculos

- `professionalLinks()` — profissionais vinculados (`ProfessionalSpecialty`,
  suporta uma especialidade "principal" por profissional).
- `serviceLinks()` — serviços vinculados (`ServiceSpecialty`).

## Criação, edição, ativação e exclusão

`/settings/specialties` (Inertia), restrito por `PermissionKey::SpecialtiesManage`.
`CreateSpecialtyAction`/`UpdateSpecialtyAction` tratam colisão de nome/código
como erro de validação amigável (`UniqueConstraintViolationException`
capturada). `DeleteSpecialtyAction` bloqueia a exclusão enquanto existirem
vínculos ativos com profissionais ou serviços — evita telas referenciando
uma especialidade excluída. `RestoreSpecialtyAction` sempre restaura com
`status = inactive` (reativação é decisão explícita separada,
`ActivateSpecialtyAction`) e revalida conflito de nome/código antes de
restaurar.

## Filtros da listagem

Status (ativas/inativas/excluídas), busca por nome/código, com/sem
profissionais vinculados, com/sem serviços vinculados — todos calculados no
frontend a partir de contagens já eager-carregadas
(`withCount(['professionalLinks', 'serviceLinks'])`), sem consulta adicional
por filtro.

## Filament

`App\Filament\Resources\Specialties\SpecialtyResource` — administração
cross-organização para o administrador da plataforma. Sem criação (nasce
pela tela Inertia); tabela identifica a clínica (`organization.name`),
ações Ativar/Inativar/Excluir/Restaurar chamam as mesmas Actions de
domínio (nunca update bruto), com feedback pt-BR e tratamento de
`ValidationException` como notificação de erro. `SpecialtyPolicy::viewAny()`
aceita organização opcional — quando ausente (navegação do Filament),
apenas `is_platform_admin` autoriza.
