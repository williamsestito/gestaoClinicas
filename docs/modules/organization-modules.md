# Módulos por especialidade

## Modelo

`App\Enums\ModuleKey` — catálogo fechado (`Core`, `Dental`, `Aesthetics`,
`Medical`, `Beauty`). `Core` nunca é desabilitável: `Organization::hasModule()`
retorna `true` para ele sem consultar o banco, e não existe linha
correspondente em `organization_modules`.

`App\Models\OrganizationModule` — uma linha por módulo habilitado ou já
alternado (organization_id, module_key, is_enabled, enabled_at, disabled_at).
Sem soft delete: é um toggle, não um cadastro com histórico de exclusão — o
histórico de habilitação/desabilitação vive em `audit_logs`
(`AuditAction::Activated`/`Deactivated`, reaproveitados do padrão de
ativação/inativação já usado por `Specialty`/`Service`).

`Organization::hasModule(ModuleKey $key): bool` é o único ponto de consulta
que qualquer feature futura deve usar para checar se um módulo está ativo —
nunca consultar `organization_modules` diretamente fora do model.

## Tela de configuração

`/settings/modules` (Inertia, tela única — sem `{module}` na URL, mesmo
padrão de `settings/organization`/`settings/seo`). Restrito por
`PermissionKey::ModulesView`/`ModulesManage` via `OrganizationModulePolicy`
(mesmo esqueleto de `SiteSettingPolicy`: platform admin → owner ativo →
permissão granular). `OrganizationModuleController::update` sempre itera o
catálogo fechado de `ModuleKey::toggleable()` — nunca as chaves recebidas no
payload — e trata a submissão como o estado completo dos 4 módulos
(um PUT que omitisse um módulo já habilitado o desabilitaria; a UI sempre
envia o conjunto completo).

`EnableOrganizationModuleAction`/`DisableOrganizationModuleAction` usam
`firstOrNew(...)->fill([...])->save()` — nunca `update()` puro, que faz
no-op silencioso em um model ainda não persistido.

## Papéis de sistema

`ModulesManage` + `ModulesView`: Owner (automático) e ClinicAdmin.
Só `ModulesView`: UnitManager e Auditor. Reception/Professional/Finance sem
acesso — mesmo padrão de granularidade de `SpecialtiesView`/`ServicesView`.

## Consumo futuro

Formulários de prontuário por especialidade (Etapa 4 do roadmap) devem
checar `$organization->hasModule(ModuleKey::Dental)` etc. antes de exibir
campos/telas específicas — o núcleo comum (paciente, agenda, financeiro)
nunca depende de nenhum módulo estar ativo.
