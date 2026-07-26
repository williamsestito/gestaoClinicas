# Busca de clínicas e pré-agendamento (fase futura — não implementado)

> **Este documento é apenas arquitetural.** Nenhum item aqui foi
> implementado nesta etapa (Fase 0.9). Não existe geolocalização, busca por
> proximidade, disponibilidade real de agenda, autocadastro de paciente ou
> reserva/confirmação automática no sistema atual. O objetivo é registrar,
> antes que o contexto se perca, como essa fase futura deve se encaixar na
> arquitetura já existente (multiempresa, auditoria, RBAC) sem exigir
> retrabalho estrutural quando for priorizada.

## O que já existe hoje (base sobre a qual a fase futura será construída)

- **Captura de lead** via `App\Actions\Public\CreateAppointmentRequestAction`
  e `appointment_requests` (nome, telefone, e-mail opcional, período
  preferido, observações, serviço de interesse, `organization_id`/`unit_id`
  opcionais). Ver `App\Models\AppointmentRequest` e
  `App\Enums\AppointmentRequestStatus` (`pending` → `contacted` →
  `scheduled`/`cancelled`).
- **Confirmação sempre manual**: a clínica muda o status pelo painel
  (`App\Http\Controllers\Organization\AppointmentRequestController`); não há
  agenda real nem horários reservados automaticamente — o comentário no
  enum já deixa isso explícito.
- **Catálogo de serviços/profissionais** público (`site_services`,
  `site_professionals`) já existe para exibição informativa na landing, mas
  sem vínculo com disponibilidade de horário.
- **Multiempresa**: toda solicitação já nasce com `organization_id`/
  `unit_id` nullable, preparada para a busca multi-clínica descrita abaixo
  sem migração adicional no momento da criação do lead.

## Fluxo futuro (visão de produto, não implementação)

1. **Localização com consentimento** — o paciente autoriza explicitamente o
   uso da localização do navegador (nunca coletada por padrão); sem
   consentimento, cai direto na busca manual.
2. **Busca manual** — por cidade/bairro/CEP quando a localização não é
   usada ou não está disponível.
3. **Filtros** — por especialidade e tipo de tratamento (`site_services`
   já modela isso; faltaria apenas indexar/filtrar por unidade e
   distância).
4. **Seleção de profissional** — a partir de `site_professionals`,
   restrito aos profissionais vinculados à unidade escolhida.
5. **Dias e horários disponíveis** — depende de uma agenda real (ver
   "Entidades necessárias" abaixo); hoje não existe nenhuma fonte de
   disponibilidade.
6. **Autocadastro do paciente** — cadastro mínimo (nome, telefone, e-mail,
   CPF opcional) fora do fluxo de `User`/membership atual — paciente não é
   um usuário do painel administrativo.
7. **Pré-agendamento** — reserva de um horário específico, com status
   inicial explícito de **"depende de confirmação"** (nunca confirmado
   automaticamente) até a clínica validar.
8. **Confirmação** — a clínica aceita, recusa ou remaneja; só então o
   horário é considerado ocupado de fato.

## Entidades que precisarão existir (ainda não existem)

- `patients` — cadastro do paciente, desacoplado de `users` (paciente não
  acessa o painel administrativo); campos mínimos descritos abaixo.
- `professional_schedules` / `availability_slots` — disponibilidade real
  por profissional/unidade/dia/horário, com granularidade suficiente para
  evitar overbooking.
- `appointments` — compromisso efetivamente confirmado, distinto de
  `appointment_requests` (que continua servindo como o lead/pré-triagem
  atual); a tabela atual **não deve virar** a agenda real — o
  relacionamento correto é `appointment_requests` → (após confirmação) →
  `appointments`.
- Tabela ou coluna de **consentimento de localização** (timestamp +
  origem), auditável como qualquer outro dado sensível.

## APIs necessárias (esboço, não contrato final)

- Busca de clínicas: `GET /api/clinicas/buscar?lat=&lng=&especialidade=&raio=`
  (ou variante manual sem `lat`/`lng`) — pública, sem autenticação, mas
  com rate limiting mais restrito que o atual (endpoint de maior custo).
- Disponibilidade: `GET /api/unidades/{unit}/profissionais/{professional}/horarios?data=`
  — pública, mas só deve expor horários livres, nunca detalhes de outros
  agendamentos (nome de outros pacientes, etc.).
- Pré-agendamento: `POST /api/pre-agendamentos` — cria o registro com
  status "depende de confirmação"; reaproveita a validação de termos já
  existente em `StoreAppointmentRequestRequest`.
- Confirmação: rota autenticada dentro do contexto de organização/unidade,
  seguindo o mesmo padrão de autorização das rotas atuais
  (`tenant.organization-membership`/`tenant.unit-membership`).

## Permissões

Reaproveitar o RBAC existente (`Role`/`Permission`/`PermissionKey`) — não
criar um sistema paralelo. Prever novas `PermissionKey` específicas (ex.:
`appointments.confirm`, `patients.view`) em vez de sobrecarregar as
permissões atuais de `appointment_requests`, já que confirmar uma consulta
real é uma ação de maior sensibilidade do que apenas gerenciar um lead.

## Privacidade e dados mínimos do paciente

- Cadastro mínimo: nome, telefone, e-mail; CPF **opcional** e só quando
  estritamente necessário (ex.: convênio), nunca obrigatório só para
  pré-agendar.
- Localização do navegador: nunca armazenada como histórico de
  deslocamento — apenas o suficiente para resolver a busca no momento;
  se for necessário persistir para auditoria de consentimento, guardar
  timestamp + fato do consentimento, não a coordenada em si além do
  necessário.
- Segue as mesmas regras já vigentes no projeto: nunca logar CPF ou dados
  de paciente em texto plano (ver `App\Support\Auditing\AuditLogger`),
  nunca excluir fisicamente um cadastro de paciente (soft delete).

## Relação com a agenda e prevenção de conflito

- Um `availability_slot` só pode gerar um `appointment` confirmado por
  vez — a reserva provisória de um pré-agendamento deve expirar
  automaticamente (TTL) se a clínica não confirmar em tempo hábil, para
  não travar o horário indefinidamente para outros pacientes.
- Confirmar um pré-agendamento deve ser uma operação atômica (lock/
  transação) que verifica se o slot ainda está livre no momento da
  confirmação, não apenas no momento em que o paciente pré-agendou.

## Integrações futuras (fora de escopo por enquanto)

- Notificação por WhatsApp/SMS de confirmação (hoje só e-mail via
  `NewAppointmentRequestNotification`/Mailpit em dev).
- Sincronização com calendários externos (Google Calendar, etc.).
- Geocodificação de endereço para a busca por proximidade (exigiria um
  provedor externo — decisão de ADR quando for priorizado).

## O que este documento explicitamente não autoriza implementar

Geolocalização real, busca por proximidade, disponibilidade de agenda,
autocadastro de paciente, reserva/confirmação de horário e qualquer
integração externa listada acima permanecem **fora de escopo** até uma
etapa futura que referencie este documento explicitamente.
