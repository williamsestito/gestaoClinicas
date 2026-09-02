# Comercial: produtos, precificação e vendas (Etapa 5)

## Escopo — o que `Venda` é e o que ela não é

O PDF de visão já separa os domínios no modelo conceitual (Seção 22):
**Comercial** = Produto, Serviço, Pacote, Venda, ItemVenda, Desconto,
Comissão — **Financeiro** = Cobrança, Parcela, Recebimento, Estorno,
ContaPagar, Caixa, Movimentação. `docs/roadmap.md` já faz a mesma divisão
entre Etapa 5 e Etapa 6. Por isso, `App\Models\Sale` **é** o registro do
que foi vendido, a que preço e com que desconto (aprovado quando
necessário) — **não** rastreia se foi pago, em quantas parcelas ou por
qual meio de recebimento, e não passa por nenhum conceito de caixa.
Cobrança/parcela/recebimento/forma de pagamento chegam na Etapa 6, como
uma camada sobre a `Venda` já existente. Nenhum campo de comissão entra
aqui pelo mesmo motivo (Seção 14.5 do PDF, "Comissões e repasses" é
Financeiro no roadmap).

## Precificação (`Service`/`Product`)

Modo simplificado do PDF (Seção 12.3): `cost_cents` (custo estimado) +
`margin_percentage` (margem desejada) informam a decisão de preço, mas o
preço praticado é sempre o valor explícito em `default_price_cents`
(`Service`) / `price_cents` (`Product`) — nunca calculado
automaticamente a partir do custo e da margem. `max_discount_percentage`
é o limite que um desconto pode ultrapassar sem exigir aprovação
(RN-010). Nenhum dos dois models ganhou campo de estoque/lote/validade —
ficam para quando o módulo de Estoque (Etapa 7) existir de verdade.

## Modelo de dados

- **`Sale`**: `organization_id`, `unit_id`, `legal_entity_id`,
  `patient_id`, `professional_id` (nullable — venda de balcão pode não
  ter profissional responsável), `appointment_id` (nullable — decisão
  explícita: venda não exige atendimento vinculado), `status`
  (`draft` / `pending_approval` / `confirmed` / `cancelled`),
  `subtotal_cents`/`discount_total_cents`/`total_cents` (sempre
  recalculados a partir dos itens, nunca confiáveis vindos do
  frontend), `created_by`. Sem soft delete — cancelamento é status,
  nunca exclusão (RN-009/RN-017).
- **`SaleItem`**: `item_type` (`service` / `product` / `service_package`),
  exatamente um de `service_id`/`product_id` preenchido conforme o tipo,
  `unit_price_cents`/`final_price_cents` são sempre um retrato do
  momento da venda — nunca relidos do catálogo depois de criados, para o
  histórico não mudar se o preço de catálogo mudar depois.
  `requires_approval`/`approved_by`/`approved_at`/`approval_justification`
  sustentam o fluxo de aprovação de desconto (RN-010/RN-011).
- **Pacote de sessões ad-hoc**: não existe um "catálogo de pacote" — um
  item `service_package` informa `service_id` + `session_count` +
  `unit_price` (preço total do pacote) diretamente na venda. Ao confirmar,
  `ConfirmSaleAction` chama `CreateSessionPackageAction` (já existente
  desde a Etapa 3.3) e grava `origin_sale_item_id` no
  `App\Models\SessionPackage` criado — pacotes criados manualmente
  continuam com esse campo nulo, comportamento intacto.

## Fluxo de aprovação de desconto (RN-010/RN-011)

Cada item calcula `requires_approval` na criação, comparando o desconto
pedido com o `max_discount_percentage` do serviço/produto (sem limite
configurado = qualquer desconto exige aprovação, por padrão conservador).
A venda inteira sobe para `pending_approval` enquanto houver algum item
pendente — `ConfirmSaleAction` recusa confirmar nesse estado.

A aprovação em si pede **justificativa e a senha do próprio aprovador**,
verificadas diretamente em `ApproveSaleItemDiscountRequest::withValidator()`
via `Hash::check()` — deliberadamente **não** usa o middleware
`password.confirm` do Fortify, porque a rota de aprovação é um PATCH via
Inertia (não uma navegação de página), e o fluxo de redirecionamento do
Fortify assume uma requisição GET. `AuditLogger::log()` captura o
**aprovador** como ator automaticamente; o `requester_user_id` (autor da
venda) vai explicitamente dentro do `after_data`, junto com preço
original, desconto e justificativa.

**Armadilha real achada pelo `security-review` de fechamento**: o preço
unitário de um item podia ser sobrescrito livremente pelo cliente
(`unit_price` no payload), e `requires_approval` só olhava para
`discount_percentage` — um usuário podia zerar o preço de um serviço de
R$ 500 informando `unit_price=0.01` com `discount_percentage=0`,
confirmando a venda sem nunca passar pela aprovação, já que "desconto"
tecnicamente era zero. Corrigido em
`CreateSaleAction::resolveServicePricing()`/`resolveProductPricing()`: o
preço de catálogo (`Service::default_price_cents`/`Product::price_cents`)
**sempre prevalece** quando existe — o `unit_price` do cliente só é usado
quando o item não tem preço de catálogo (ex.: pacote de sessões ad-hoc,
que não tem preço de tabela). Qualquer redução de preço precisa passar
pelo campo `discount_percentage`, que é o único caminho comparado contra
`max_discount_percentage`. O frontend (`sales/Create.vue`) reflete a
mesma regra: o campo de preço unitário fica desabilitado quando o item
tem preço de catálogo. Coberto por teste dedicado em
`SaleLifecycleTest.php`.

## Autorização

Dados comerciais **não são clínicos** — diferente de `MedicalRecordPolicy`
(Etapa 4), `SalePolicy`/`ProductPolicy` usam o padrão normal do resto do
app (`PermissionChecker::can()`, que libera owner/platform-admin), sem
nenhum desvio de RN-015/016. Permissões novas: `products.view`,
`products.manage`, `sales.view`, `sales.manage`, `sales.manage-own`,
`sales.approve-discount`.

**Armadilha real evitada em teste, achada antes de fechar a etapa**:
`SalePolicy::create()` só consegue checar a permissão em nível de
organização — o paciente da venda ainda não existe como recurso no
momento da checagem. Sem uma segunda verificação, um usuário com apenas
`sales.manage-own` (ex.: profissional) conseguiria criar uma venda para o
paciente de um colega só por ter a permissão "própria" concedida — mesma
categoria de IDOR já corrigida no autoatendimento de agendamento da Etapa
3.7. Corrigido com `CreateSaleAction::assertPatientAccessible()` (também
chamado por `UpdateSaleDraftAction`): quando o usuário não tem
`sales.manage` amplo, exige que `patient.primary_professional_id`
corresponda ao profissional vinculado ao usuário — mesma definição de
"paciente próprio" já usada em `PatientPolicy`/`AppointmentPolicy`. O
mesmo método também valida `professional_id`, quando informado, contra o
profissional vinculado ao usuário — sem isso, um profissional autorizado
só pela via "própria" poderia atribuir a venda a um colega (achado de
menor severidade, mas corrigido no mesmo lugar). Coberto por teste
dedicado em `SaleAccessTest.php`.

## UI

`settings/products` e `settings/sales` (grupo de navegação "Comercial").
"Vender" a partir da ficha do paciente (`settings/patients`,
`settings/my-patients`) abre `settings/sales/create?patient_id=...` com o
paciente pré-preenchido — mesmo padrão do link "Prontuário" da Etapa 4.
O carrinho de itens (`sales/Create.vue`) calcula total/aviso de aprovação
pendente ao vivo no cliente, mas o servidor sempre recalcula tudo de novo
— o preview do cliente é só UX, nunca fonte de verdade.
