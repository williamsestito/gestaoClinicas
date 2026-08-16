# Roadmap de evolução

Documento de retomada: para onde o projeto vai a partir do estado atual e em
que ordem. Substitui a seção "Próximas fases" de
[modules/README.md](modules/README.md) como fonte única de verdade sobre
progresso — atualizar aqui a cada etapa fechada, não lá.

Cada etapa é dimensionada para caber em uma a três sessões de trabalho
focadas. Não abrir a etapa seguinte antes de fechar a checklist da atual.

## Regra de fechamento de etapa (vale para todas)

Uma etapa só é considerada concluída quando:

1. Migrations + Models + Actions + Policies + Form Requests seguem o padrão
   já usado em `app/Actions/Organization/*` (soft delete, `organization_id`/
   `unit_id` explícitos, sem exclusão física).
2. Testes Pest cobrem: isolamento multiempresa (organização/unidade não vaza
   para outra), matriz de papéis/permissões da etapa, e as regras de negócio
   críticas listadas no PDF de visão (ex.: menor sem responsável, desconto
   acima do limite, prontuário finalizado imutável).
3. `App\Support\Auditing\AuditLogger` é chamado explicitamente em toda Action
   que o PDF marca como evento auditável (seção 19.1 do documento de visão).
4. `vendor/bin/pint --dirty --format agent` e `composer analyse` (Larastan +
   vue-tsc) rodam limpos.
5. Skill `security-review` roda sobre o diff da etapa antes de fechar —
   principal barreira contra "brechas" (autorização incorreta, dado clínico
   vazando para papel administrativo, etc.).
6. `docs/modules/<modulo>.md` é criado/atualizado e este roadmap é marcado.

Rodar apenas os testes afetados (`php artisan test --filter=...`) durante o
desenvolvimento da etapa; rodar a suíte completa relevante só ao fechar.

## Estado atual (referência rápida)

Implementado: fundação técnica, multiempresa (Organization/LegalEntity/Unit +
`TenantContext`), RBAC completo, Especialidades, Serviços, Profissionais
(cadastro, jornada, ausências, vínculos), site público + SEO, leads de
pré-agendamento (`AppointmentRequest`, sem reserva real), módulos
habilitáveis por especialidade (`ModuleKey`/`OrganizationModule`), cadastro
administrativo de pacientes (`Patient`/`PatientResponsible`/
`PatientEmergencyContact`), agenda real com conflito de verdade
(`Appointment`, núcleo — sem recursos/salas ainda), autocadastro/login/
portal do paciente (`PatientUser`/`PatientUserLink`, guard próprio,
dependentes, sem booking ainda).

Não existe: Recursos compartilhados (salas/equipamentos), Prontuário,
Produto/Venda/Financeiro/Estoque, booking público de verdade, mesclagem de
pacientes, assinatura/billing.

---

## Etapa 1 — Módulos por especialidade (fundação transversal) ✅ concluída em 2026-08-15

**Por que primeiro:** Prontuário (Etapa 4) precisa de formulários distintos
por especialidade (odontológico, estética, médico, centro de beleza).
Construir o encaixe agora evita retrabalho quando essas telas existirem.

Entregue: `App\Enums\ModuleKey`, tabela/model `organization_modules`,
`Organization::hasModule()`, `Enable/DisableOrganizationModuleAction`,
permissões `modules.view`/`modules.manage`, `OrganizationModulePolicy`,
tela `settings/modules` (proprietário habilita/desabilita Dental,
Aesthetics, Medical, Beauty — Core é implícito). Ver
[docs/modules/organization-modules.md](modules/organization-modules.md).
Testes:
`tests/Feature/OrganizationModules/OrganizationModuleManagementTest.php`
(10 casos, isolamento multiempresa e permissão granular cobertos).
Security-review do diff: sem achados de alta confiança.

- Enum `ModuleKey` (`core`, `dental`, `aesthetics`, `medical`, `beauty`).
- Tabela `organization_modules` (organization_id, module_key, enabled_at,
  disabled_at) — múltiplos módulos ativos simultaneamente por organização.
- `Organization::hasModule(ModuleKey $key): bool` + gate em Policy/middleware.
- Tela em `settings/` (ou recurso Filament) para o próprio proprietário
  habilitar/desabilitar módulos — mantém "autogerenciamento" do princípio 1.5
  do PDF.
- **Não** inclui ainda nenhum formulário clínico específico — só o
  interruptor e o mecanismo de leitura (`hasModule`).

## Etapa 2.1 — Pacientes: cadastro administrativo ✅ concluída em 2026-08-15

Entregue: `Patient` (nome, nome de preferência, CPF opcional, data de
nascimento obrigatória, contato, endereço opcional em bloco, foto opcional,
unidade/profissional de preferência), `PatientResponsible` (contato simples
— nome/CPF opcional/telefone/relação + flags legal/financeiro/representante,
**não** é outro `Patient`, decisão registrada em
[docs/modules/patients.md](modules/patients.md)), `PatientEmergencyContact`
(RN-003, ao menos um ativo por paciente). RN-004 (menor de 18 exige
responsável legal) reforçada em `App\Support\Patients\MinorGuardianGuard`,
tanto na criação (contra o payload) quanto na atualização (contra os
registros já persistidos). Busca de duplicidade por igualdade exata
(documento/telefone/e-mail/nome+nascimento) — só aviso, nunca bloqueio.
Permissões `patients.view`/`patients.manage`/`patients.view-own`.
Foto do paciente em disco privado (`local`), servida só por
`PatientController::showPhoto()` atrás de `PatientPolicy::view()` — nunca
por link público direto (diferente da foto do profissional, propositalmente
pública). Testes: `tests/Feature/Patients/*` (39 casos, incluindo
`PatientPhotoManagementTest` confirmando que o arquivo nunca vai para o
disco `public` e que o endpoint bloqueia acesso de fora da organização).
Security-review do diff original apontou um achado Medium (foto em disco
público) — corrigido no mesmo dia, ver
[docs/modules/patients.md](modules/patients.md#foto-do-paciente--armazenamento-privado).

## Etapa 2.2 — Pacientes: autocadastro, portal e dependentes ✅ concluída em 2026-08-15

Entregue: autocadastro público (RF-AUT-002) e login do paciente/responsável
em guard próprio (`patient`, tabela `patient_users`), completamente
separado do guard `web`/Fortify de staff — evita colidir com o e-mail
único de `users` e evita herdar comportamento pensado para staff. Uma
conta pode gerenciar vários pacientes (titular e/ou dependentes) via
`App\Models\PatientUserLink`, tabela de junção real com dois índices
únicos parciais (um vínculo `self` por conta; uma conta por paciente —
resolve também o "portal do responsável" que a Etapa 2.1 tinha deixado em
aberto). Nenhuma Policy é usada no portal — decisão deliberada, documentada
em [docs/modules/patient-portal.md](modules/patient-portal.md), já que
Policies deste projeto resolvem por classe de model contra o guard
default; toda rota escopa o `Patient` através do relacionamento
(`$patientUser->patients()->findOrFail()`), 404 em vez de vazar existência
de registro de outra conta. RN-003 relaxada para o titular adulto
autocadastrado (sem contato de emergência forçado); RN-004 continua valendo
para dependentes menores (responsável legal auto-criado). Rate limiting e
honeypot/tempo-mínimo próprios (mesmo padrão já usado no formulário
público de agendamento). Um bug real de guard-fallout foi corrigido:
`AuditLogger::log()` usava `Auth::id()` sem guard, o que testes do portal
expuseram como incorreto (`actingAs()` troca o guard default do
`AuthManager`) — corrigido para `Auth::guard('web')->id()`. Adicionar o
segundo guard também quebrou a inferência de tipo do Larastan em ~10
arquivos de staff pré-existentes (correção mecânica, sem mudança de
comportamento — guard `web` explicitado onde antes era implícito). Testes:
`tests/Feature/PatientPortal/*` (25 casos). Security-review do diff:
ver [docs/modules/patient-portal.md](modules/patient-portal.md).

**Fora desta etapa (decisão registrada):** mesclagem de cadastros
duplicados preservando histórico — continua adiada porque ainda não
existem agenda/prontuário/financeiro para reatribuir; construir junto com
essas fases evita retrabalho. Booking pelo portal (Etapa 3.2, agora
desbloqueada por esta etapa), remover/desvincular dependente, segundo
responsável com login próprio para o mesmo paciente, 2FA/passkeys para
paciente, CAPTCHA além do honeypot/timing já existente.

## Etapa 3.1 — Agenda real: núcleo ✅ concluída em 2026-08-15

Entregue: `Appointment` (status completo do PDF — seção 8.4 — ainda que só
`Confirmed` em diante seja alcançável nesta etapa, já que só staff cria),
conflito real via `App\Support\Availability\AppointmentOverlapGuard`
(contra jornada/bloqueios, reaproveitando `ProfessionalAvailabilityResolver`
sem alteração, e contra outros agendamentos do mesmo profissional, com
advisory lock do Postgres), sugestão de horário via
`App\Services\Availability\StaffAppointmentSlotFinder`, ciclo completo
(check-in/início/conclusão/não comparecimento), reagendamento e
cancelamento com motivo (histórico via `AuditLogger`, sem auto-referência
de model). Permissões `appointments.view`/`manage`/`view-own`/`manage-own`.
Ver [docs/modules/appointments.md](modules/appointments.md) — inclui o
relato de um bug real de timezone encontrado e corrigido pelos próprios
testes, e o achado de security-review (race condition no guard de
conflito) corrigido no mesmo dia. Testes:
`tests/Feature/Appointments/*` (54 casos).

**Fora desta etapa (3.2, decisão registrada):** recursos compartilhados
(salas/equipamentos — nenhuma base no código, domínio novo inteiro),
booking público (depende da Etapa 2.2, login do paciente), conversão de
`AppointmentRequest` em `Appointment` com um clique, pacotes de sessões,
recorrência, lista de espera, "encaixe" configurável (sobreposição é
sempre bloqueada nesta etapa).

## Etapa 4 — Prontuário e documentos

- `MedicalRecord` versionado: rascunho editável, finalização imutável,
  correção só por adendo (RN-007).
- Separação de acesso: só profissional autor ou usuário clínico autorizado
  cria/vê conteúdo clínico (RN-006, RN-016 — proprietário não tem acesso
  clínico automático).
- Upload/captura de arquivos (PDF/JPEG/PNG), categorias, auditoria de
  visualização/download/exportação (RN-008).
- Formulários por especialidade usam o `ModuleKey` da Etapa 1 (núcleo comum
  sempre disponível; odontograma fica para pós-MVP conforme o próprio PDF).
- Portal do paciente passa a exibir registros finalizados e liberados
  (RN-014).

## Etapa 5 — Comercial (produtos, serviços, vendas)

- `Product`, precificação em modo simplificado primeiro (custo + margem +
  desconto máximo); modo avançado é incremento posterior, não bloqueia MVP.
- `Sale`/`ItemVenda` vinculados a paciente, profissional, unidade, entidade
  legal e atendimento.
- Aprovação de desconto acima do limite com justificativa e log completo
  (RN-010, RN-011).

## Etapa 6 — Financeiro

- Cobrança, parcela, recebimento — nunca exclusão física, sempre
  cancelamento/estorno/renegociação (RN-009).
- Caixa (abertura, sangria, suprimento, fechamento, reabertura autorizada) e
  contas a pagar/receber.
- Comissões e repasses básicos.

## Etapa 7 — Estoque

- Fornecedores, compras, lotes/validade, inventário, consumo automático
  vinculado a serviço quando configurado.

## Etapa 8 — Relatórios e dashboards

- Depende de dados reais das Etapas 5-7. Filtros mínimos da seção 17 do PDF
  (período, organização, entidade legal, unidade, profissional,
  especialidade, serviço, produto, paciente, status, usuário, forma de
  pagamento).

## Etapa 9 — Assinatura, trial e cobrança da plataforma (billing)

Independente das Etapas 2-8 em termos técnicos — pode ser adiantada se
houver pressão comercial para cobrar clínicas-piloto antes do MVP completo
(ver "pontos de decisão" abaixo).

- `Plan` (limites: unidades, usuários, storage) e `Subscription`
  (organization_id, status: trialing/active/past_due/canceled/expired,
  trial_ends_at, current_period_end).
- Toda organização nasce em `trialing` por N dias configuráveis — cobre o
  caso de "acesso limitado para teste" sem precisar de mecanismo à parte.
- Middleware `EnsureSubscriptionIsActive` bloqueia rotas operacionais fora de
  `trialing`/`active`, com período de carência antes do bloqueio duro.
- Integração com gateway de assinatura recorrente com boleto/PIX (Asaas,
  Iugu, Vindi ou Pagar.me — mais aderente ao público de clínicas pequenas no
  Brasil que Stripe puro) via webhook assinado.
- Job diário reavalia trials/vencimentos e dispara notificação (reusa o
  módulo de Comunicação).

## Etapa 10 — Hardening, segurança e piloto

- Checklist completo RNF-SEG-001 a 008 revisado item a item.
- `security-review` sobre o sistema inteiro (não só o diff da última etapa).
- Teste de backup/restauração, teste de carga básico.
- Migração assistida das clínicas-piloto, treinamento, central de ajuda.

---

## Pontos de decisão de negócio (revisar antes de travar a ordem)

- **Billing (Etapa 9) pode subir de posição** se houver necessidade de
  cobrar as clínicas-piloto antes do módulo comercial/financeiro estar
  pronto — os dois sistemas de "dinheiro" são independentes (um cobra a
  clínica pela plataforma, o outro é o financeiro da própria clínica).
- **Estoque (Etapa 7) pode ser adiada** para pós-MVP se o segmento piloto
  escolhido (PDF recomenda estética/massoterapia/bem-estar) não depender de
  controle de lote para o piloto inicial.

## Economia de tokens por sessão

- Abrir cada sessão lendo **só** a seção da etapa corrente aqui + o(s)
  `docs/modules/*.md` relacionado(s) — não re-explorar o repo inteiro.
- Uma etapa = um branch, um conjunto de commits coeso; evitar reabrir
  etapas já fechadas para "melhorias" fora de escopo.
- Preferir edição direta de arquivos existentes a regenerar módulos inteiros;
  usar sub-agentes de exploração só na abertura da etapa, não durante ela.
- Não construir abstração para módulo/etapa futura antes dela chegar (ex.:
  não desenhar odontograma agora só porque o PDF menciona no pós-MVP).
