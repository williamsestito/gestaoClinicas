# Entidades legais

## Modelo

`App\Models\LegalEntity` — pessoa física (CPF) ou jurídica (CNPJ)
responsável pela organização. `document` armazenado sempre sem máscara
(só dígitos) e único no banco. Uma organização tem exatamente uma entidade
principal (`is_primary = true`), garantido por índice único parcial em
PostgreSQL (`legal_entities_one_primary_per_org`).

## CPF/CNPJ

Validação e normalização centralizadas em
`App\Support\Documents\Document` (Value Object imutável — só é possível
existir uma instância com dígitos verificadores válidos) e
`App\Rules\CpfCnpjRule` (para Form Requests). Nenhum pacote externo foi
usado. `Document::masked()` mantém só os 2 últimos dígitos — é essa versão
que vai para os logs de auditoria (nunca o documento completo).

## Criação e edição

Criada junto da organização, no onboarding
(`App\Actions\Organization\CreateLegalEntityAction`). Edição (Inertia via
`UpdateLegalEntityAction`, ou Filament) é limitada a
`legal_name`/`trade_name`/inscrições/e-mail/telefone — tipo, documento e
`is_primary` não são editáveis nesta fase (mudar o documento de uma
entidade ativa é uma operação sensível, fora do escopo aqui). Restrito ao
proprietário da organização. Sem exclusão física.
