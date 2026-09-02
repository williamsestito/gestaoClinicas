# Pacientes

## Modelo

`App\Models\Patient` — pertence a uma organização (não a uma unidade — ver
decisão abaixo). `document` (CPF) opcional, único por organização ignorando
soft-deleted (índice parcial, mesmo padrão de `professionals`). `birth_date`
é **obrigatório** (diferente de `Professional`, onde é opcional) — é o único
jeito de a regra de menor (RN-004) ser sempre verificável. `morphOne`
`Address` (registrado no morph map como `patient`).

## Responsável ≠ Paciente

`App\Models\PatientResponsible` é uma entidade dependente simples (nome,
CPF opcional, telefone, relação, flags `is_legal_guardian`/
`is_financial_responsible`/`is_authorized_representative` — não exclusivas
entre si), **não** uma auto-referência de `Patient`. Essa foi uma decisão
deliberada: evita introduzir a primeira auto-referência de model do projeto
e evita forçar o cadastro completo de alguém que talvez nunca seja
atendido. Se esse responsável precisar de atendimento próprio um dia, vira
um `Patient` separado sem vínculo automático.

Consequência que era um problema em aberto até a Etapa 2.2: o "portal do
responsável" — resolvido sem criar nenhuma FK entre `PatientResponsible` e
o novo `App\Models\PatientUserLink` (conta de portal); ver
`docs/modules/patient-portal.md` para o desenho completo.

## RN-003 e RN-004

`App\Support\Patients\MinorGuardianGuard` centraliza as duas checagens:

- **RN-003** (contato de emergência obrigatório): `emergency_contacts` é
  obrigatório com mínimo 1 na criação; `DeletePatientEmergencyContactAction`
  bloqueia remover o último contato ativo.
- **RN-004** (menor de 18 exige responsável legal): na criação, contra o
  array `responsibles` recém-enviado no mesmo POST (`hasLegalGuardianInPayload`);
  na atualização — quando `birth_date` muda para menor de 18 —, contra os
  registros já persistidos (`hasOtherActiveLegalGuardian`).
  `Update/DeletePatientResponsibleAction` bloqueiam remover/desmarcar o
  único responsável legal ativo de um paciente menor.

## Duplicidade

`App\Queries\PatientDuplicateQuery` — busca **exata** (documento, telefone/
WhatsApp, e-mail, ou nome+data de nascimento combinados), nunca por
similaridade/nome parecido, mesma filosofia já testada em
`PublicProfessionalQueryTest`. Endpoint JSON (`GET /settings/patients/duplicates`)
consultado pelo formulário antes do submit — só avisa, nunca bloqueia a
criação. Mesclagem de fato (Etapa 2.2) ainda não existe.

**Bug real encontrado e corrigido (2026-08-22)**: a validação de CPF único
(`CreatePatientRequest`/`UpdatePatientRequest`/
`UpdatePatientPortalProfileRequest`) checava a tabela `patients` inteira,
sem excluir registros arquivados (soft-deleted) — diferente do índice
único de verdade no banco (`patients_unique_active_document`, ver
migration de `patients`), que já é parcial (`WHERE deleted_at IS NULL`).
Na prática, um paciente arquivado com um CPF X bloqueava **para sempre**
qualquer novo cadastro com esse CPF — inclusive o próprio dono do CPF
tentando apenas salvar o cadastro ativo dele sem mudar nada. Corrigido
adicionando `->whereNull('deleted_at')` às três regras, espelhando o
índice do banco.

## Listagem paginada no servidor

Diferente de `Specialty`/`Service`/`Professional` (listagem inteira sem
paginação, filtrada no cliente), `App\Queries\PatientListQuery` pagina e
filtra no servidor — volume de pacientes tende a crescer bem além do de
profissionais.

## Autorização

`PermissionKey::PatientsView`/`PatientsManage`/`PatientsViewOwn`.
`PatientsManage` vai para `ClinicAdmin` **e para `Reception`** (recepção é
quem cadastra pacientes no dia a dia). `PatientsViewOwn` cobre o
profissional vinculado como `primary_professional_id` (só leitura).
`PatientPolicy::viewAny()`/`view()` exigem explicitamente uma dessas
permissões — diferente de `ProfessionalPolicy::viewAny()`, que libera
qualquer membro ativo da organização. Essa é uma divergência deliberada:
dado pessoal de paciente é mais sensível que um diretório de profissionais.

## Autoatendimento — "Meus pacientes"

`App\Http\Controllers\Organization\MyPatientsController` (rota
`settings/meus-pacientes`, mesmo padrão de "Minha agenda": nunca aceita
`professional_id` da URL, sempre resolvido a partir do usuário
autenticado) lista só pacientes com `primary_professional_id` apontando
para o profissional vinculado ao usuário logado — nunca a base completa da
clínica, que continua exigindo `PatientsView`/`PatientsManage`. Reaproveita
`App\Queries\PatientListQuery` com o novo parâmetro opcional
`primaryProfessionalId`. Cada linha da lista aponta para a mesma tela
`settings.patients.edit` já usada pelo staff — `PatientPolicy::view()` já
autoriza o próprio profissional vinculado, então não existe uma tela de
"visualização" separada; o formulário aparece editável, mas
`PatientPolicy::update()` continua bloqueando a gravação para quem só tem
`PatientsViewOwn` (sem `PatientsManage`).

## Foto do paciente — armazenamento privado

Diferente da foto do profissional (disco `public`, propositalmente exposta
via link direto — o profissional tem opt-in explícito `is_public` para a
vitrine do site), a foto do paciente é dado privado sem nenhuma finalidade
pública. Fica no disco `local` (`storage/app/private`, sem symlink público)
e só é servida por `PatientController::showPhoto()`
(`GET settings/patients/{patient}/photo`), atrás da mesma
`PatientPolicy::view()` usada para o restante do cadastro — nunca por URL
direta. `photo_url` exposto ao frontend já é essa rota autenticada, não um
link de storage. Isso não é inconsistência com o profissional: são posturas
diferentes porque a exposição pretendida de cada um é genuinamente
diferente (uma pode virar pública por decisão do profissional; a outra
nunca deveria).

## Fora do escopo desta etapa (2.1)

Autocadastro público, login do paciente, portal do paciente (todos
entregues na Etapa 2.2, ver `docs/modules/patient-portal.md`), mesclagem
de duplicados, etiquetas/tags, preferências de canal de comunicação,
Filament resource — ver `docs/roadmap.md`.
