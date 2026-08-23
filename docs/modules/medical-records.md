# Prontuário clínico (Etapa 4)

## Modelo

`App\Models\MedicalRecord` é o registro clínico de um atendimento —
sempre vinculado 1:1 a um `App\Models\Appointment` (`appointment_id`
único). Não existe entidade "Atendimento" separada: o `Appointment` já
existente (agenda real, ver [appointments.md](appointments.md)) é
reaproveitado como o contexto do prontuário, mesmo padrão de outros
recursos que penduram em `Appointment` (pacotes de sessão, etc.).

`CreateMedicalRecordAction` é idempotente — abrir o prontuário de um
atendimento que já tem um registro apenas retorna o existente, nunca cria
um segundo. Não há rota de exclusão em nenhuma camada (sem soft delete,
sem `forceDelete`): RN-007 ("prontuário finalizado não pode ser excluído
ou sobrescrito") é garantido estruturalmente, não só por autorização.

### Ciclo de vida: rascunho → finalizado, correções por adendo

`status` (`draft`/`finalized`) controla tudo:

- **Rascunho**: só o autor (via `MedicalRecordsManageOwn`) ou um usuário
  com `MedicalRecordsManage` pode editar livremente
  (`UpdateMedicalRecordDraftAction`).
- **Finalizado** (`FinalizeMedicalRecordAction`, carimba `finalized_at`):
  os campos clínicos ficam imutáveis — `UpdateMedicalRecordDraftAction`
  recusa (`ValidationException`) qualquer tentativa de update direto, e a
  Policy também bloqueia a rota antes disso. A única forma de corrigir um
  registro finalizado é um **adendo** (`MedicalRecordAddendum`, tabela
  só-de-criação — sem rota de update/delete): preserva o conteúdo e autor
  originais, RN-017 ("operações críticas sempre preservam histórico e
  autor").
- **Liberado ao paciente** (`released_to_patient_at`, independente de
  `finalized_at`): dois carimbos distintos porque finalizar não implica
  automaticamente visibilidade no portal — RN-014 exige uma liberação
  explícita separada (`ReleaseMedicalRecordToPatientAction`).

### Retorno (15 dias)

`has_return_right`/`return_window_days` ficam no próprio `MedicalRecord`
(não uma entidade separada) — o profissional marca se o paciente tem
direito a retorno ao finalizar, com prazo padrão de 15 dias.

## Autorização — desvio deliberado do padrão do resto do app

Em todo outro domínio (`ProfessionalPolicy`, `PatientPolicy`, etc.), o
caminho "amplo" de autorização é `owner || permissão`, via
`App\Support\Authorization\PermissionChecker::can()` — que **sempre**
libera acesso total para `is_platform_admin`/`is_owner` antes mesmo de
olhar o papel atribuído (ver docblock do próprio `PermissionChecker`).

RN-015 ("administrador da plataforma não possui acesso clínico
automático") e RN-016 ("proprietário administrativo não possui acesso
clínico irrestrito só por ser proprietário") proíbem exatamente esse
bypass para dados clínicos. Por isso `MedicalRecordPolicy` **nunca** chama
`PermissionChecker::can()` em nenhum dos dois caminhos — o método privado
`hasClinicalAccess()` consulta diretamente, via `hasPermission()`, se o
papel atribuído ao vínculo realmente tem `medical-records.manage`
(caminho amplo) ou `medical-records.manage-own` (caminho do próprio
autor), sem nenhum atalho de owner/admin em nenhum dos dois.

**Armadilha real que já causou um bug nesta etapa**: fechar o atalho no
código da Policy não basta se o papel do proprietário *realmente tiver* a
permissão — `SystemRole::Owner->defaultPermissions()` originalmente
retornava `PermissionKey::cases()` (todas as permissões, para consistência
de exibição), e `SeedSystemRolesAction` grava esse conjunto de verdade no
papel "Proprietário" de cada organização. Como `MedicalRecordPolicy`
consulta a permissão real do papel (não um atalho), todo proprietário
passava a ter acesso clínico irrestrito de qualquer jeito — o mesmo
problema por uma porta diferente, achado pelo `security-review` de
fechamento desta etapa. **Corrigido** excluindo explicitamente
`MedicalRecordsManage`/`MedicalRecordsManageOwn` do conjunto padrão do
proprietário em `SystemRole::Owner->defaultPermissions()`. Qualquer nova
`PermissionKey` sensível adicionada no futuro precisa do mesmo cuidado se
alguma Policy vier a consultar permissões de papel diretamente em vez de
usar `PermissionChecker::can()`.

Duas permissões novas: `medical-records.manage-own` (adicionada ao papel
padrão "Profissional") e `medical-records.manage` (**não** adicionada a
nenhum papel padrão — só existe se a própria clínica criar um papel
customizado, ex. "Responsável técnico", e conceder essa permissão
explicitamente). Recepção, financeiro e estoque nunca têm acesso, mesmo
sendo proprietário ou administrador — RN-006/015/016

**Segunda armadilha real, achada em teste manual pós-fechamento**:
`SeedSystemRolesAction::syncWithoutDetaching` só roda de fato quando é
chamada — e no código-base ela só era invocada em
`OnboardOrganizationAction` (organização nova). Nada re-executava para
organizações já existentes quando `MedicalRecordsManageOwn` passou a
fazer parte do conjunto padrão do papel "Profissional" — na prática, todo
profissional de toda clínica criada antes desta etapa continuava sem a
permissão, apesar do papel já existir. Adicionado
`php artisan app:sync-system-roles` (`app/Console/Commands/SyncSystemRoles.php`)
para rodar `SeedSystemRolesAction::handle()` em todas as organizações —
idempotente e só aditivo, nunca remove uma customização feita pelo
administrador. **Rodar este comando sempre que uma `PermissionKey` nova
entrar no conjunto padrão de um papel de sistema já em uso** (o mesmo
problema vai se repetir em qualquer etapa futura que faça isso).
cobertos por teste de regressão dedicado
(`tests/Feature/MedicalRecords/MedicalRecordAccessTest.php`).

## Documentos e arquivos

`MedicalRecordFile` guarda upload de PDF/JPEG/PNG em disco privado
(`Storage::disk(...)`), categorizado por `MedicalRecordFileCategory`
(Exame, Fotografia clínica, Prescrição, Atestado/Declaração, Consentimento,
Encaminhamento, Laudo) — só as categorias clinicamente sensíveis do PDF de
visão entram nesta etapa; categorias puramente administrativas (Contratos,
Documentos pessoais, Comprovantes) ficam fora. Validação de conteúdo real
(não só extensão/MIME) via `App\Rules\ValidPdfContentRule` (assinatura
`%PDF-`) e o já existente `ValidImageContentRule`.

Toda visualização e download são auditados explicitamente
(`AuditAction::Viewed`/`Downloaded`) — RN-008. Não há recorte/rotação/
correção de perspectiva na captura por câmera (RF-ARQ-002 do PDF):
`<input type="file" capture>` já habilita a câmera do celular, e o
processamento de imagem fica fora do MVP.

## Modelos por especialidade

`specialty_data` (JSON, nullable) evita inventar um form-builder genérico
— cada módulo grava seu próprio formato nesse campo, sem alterar o núcleo
de colunas. Só o núcleo comum (anamnese, evolução, prescrições, exames,
consentimentos) está com UI pronta nesta etapa; os formulários adicionais
de Estética e Massagens/terapias (avaliação/contraindicações/protocolo,
mapa corporal/queixas/restrições) ficam para quando esses módulos forem
ativados de fato. **Odontograma está fora** (Seção 2.2 do PDF de visão —
"fora do primeiro lançamento comercial" — e Fase 8 do plano de entregas).

## Portal do paciente

`App\Http\Controllers\PatientPortal\MedicalRecordController` segue o
mesmo padrão anti-IDOR do resto do portal (ver
[patient-portal.md](patient-portal.md)): nunca usa Policy nem
route-model-binding direto em `Patient` — sempre
`$patientUser->patients()->findOrFail($patient)`. A query já filtra
`status = finalized AND released_to_patient_at IS NOT NULL` — um registro
rascunho, ou finalizado mas ainda não liberado, nunca aparece. Não há rota
de download de arquivo no portal ainda (só nome do arquivo e categoria são
exibidos) — ficou fora do escopo desta etapa.

## Lembrete no dashboard (nunca bloqueia)

Ao concluir um atendimento, nada impede a conclusão por falta de
prontuário — decisão explícita do produto. `DashboardController::professionalDashboardData()`
só soma um contador aditivo (`completedWithoutMedicalRecordCount`) e expõe
`medical_record_id` por item de agenda, para o dashboard do profissional
mostrar um lembrete visual e um link direto para abrir o prontuário.
`CompleteAppointmentAction`/`AppointmentController::complete()` não foram
alterados.
