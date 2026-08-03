# Registros profissionais

## Modelo

`App\Models\ProfessionalRegistration` — registro em conselho de classe
(ex.: CRM/SP 123456) de um `Professional`. `council` é texto livre (o
catálogo de conselhos varia por tipo de profissional; um enum fechado seria
frágil). Um profissional pode ter mais de um registro; no máximo um ativo
pode ser `is_primary`, garantido por índice único parcial — nunca apenas
por lógica de aplicação.

## Vigência

`ProfessionalRegistrationValidityStatus` computa, a partir de
`issued_at`/`expires_at`: válido, a vencer em breve, vencido, ou sem data
de expiração. Não bloqueia nenhuma operação por si só — é informativo,
alimentando o `warnings` do resolvedor de situação operacional (ver
[professionals.md](professionals.md#situação-operacional)) quando o
registro principal está vencido.

## Número completo — dado sensível

O número de registro completo nunca é enviado nas props de listagem por
padrão (`ProfessionalRegistration::maskedRegistrationNumber()` mascara,
mantendo poucos caracteres visíveis). Revelar o número completo exige a
permissão específica `PermissionKey::ProfessionalRegistrationsViewSensitive`,
verificada em rota dedicada — nunca embutida na resposta padrão da tela.
Nunca gravado em texto puro no log de auditoria (`AuditLogger` mascara
chaves como `registration_number` recursivamente).

## Criação, edição, principal, ativação e exclusão

`/settings/professionals/{professional}/registrations`, restrito por
`PermissionKey::ProfessionalRegistrationsManage`. Definir principal é
atômico (o antigo principal deixa de ser, o novo passa a ser, numa única
transação). Exclusão bloqueada quando é o registro principal e existem
outros registros ativos — evita deixar o profissional sem principal
silenciosamente.
