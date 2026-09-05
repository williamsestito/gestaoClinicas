<?php

declare(strict_types=1);

namespace App\Data\Organization;

use App\Enums\LegalEntityType;
use App\Support\Documents\Document;
use InvalidArgumentException;

/**
 * Dados para um platform admin criar a organização de produção pelo painel
 * (ver App\Actions\Organization\BootstrapOrganizationAction) — nunca define
 * senha do administrador diretamente: ou vincula um usuário já existente, ou
 * dispara um convite (App\Actions\Organization\InviteUserAction) para que a
 * própria pessoa defina a senha ao aceitar.
 */
final readonly class BootstrapOrganizationData
{
    public function __construct(
        public string $organizationName,
        public LegalEntityType $legalEntityType,
        public string $document,
        public string $legalName,
        public ?string $tradeName,
        public string $unitName,
        public ?string $unitPhone,
        public ?string $unitWhatsapp,
        public AddressData $address,
        public ?string $existingOwnerUserId,
        public ?string $inviteName,
        public ?string $inviteEmail,
    ) {
        if (($existingOwnerUserId === null) === ($inviteEmail === null)) {
            throw new InvalidArgumentException(
                'Informe exatamente um administrador: um usuário existente ou um convite por e-mail.',
            );
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        // O Radio do wizard (ver CreateOrganization) já entrega o valor como
        // instância de LegalEntityType (cast automático do Filament pelas
        // options do enum) — aceita também string crua para quem monta os
        // dados fora do formulário (ex.: testes).
        $type = $data['legal_entity_type'] instanceof LegalEntityType
            ? $data['legal_entity_type']
            : LegalEntityType::from((string) $data['legal_entity_type']);

        return new self(
            organizationName: (string) $data['organization_name'],
            legalEntityType: $type,
            document: Document::onlyDigits((string) $data['document']),
            legalName: (string) $data['legal_name'],
            tradeName: isset($data['trade_name']) && $data['trade_name'] !== '' ? (string) $data['trade_name'] : null,
            unitName: (string) $data['unit_name'],
            unitPhone: isset($data['unit_phone']) && $data['unit_phone'] !== '' ? (string) $data['unit_phone'] : null,
            unitWhatsapp: isset($data['unit_whatsapp']) && $data['unit_whatsapp'] !== '' ? (string) $data['unit_whatsapp'] : null,
            address: AddressData::fromArray((array) $data['address']),
            existingOwnerUserId: isset($data['existing_owner_user_id']) && $data['existing_owner_user_id'] !== ''
                ? (string) $data['existing_owner_user_id']
                : null,
            inviteName: isset($data['invite_name']) && $data['invite_name'] !== '' ? (string) $data['invite_name'] : null,
            inviteEmail: isset($data['invite_email']) && $data['invite_email'] !== '' ? mb_strtolower((string) $data['invite_email']) : null,
        );
    }
}
