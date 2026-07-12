<?php

declare(strict_types=1);

namespace App\Data\Organization;

use App\Enums\LegalEntityType;
use App\Support\Documents\Document;

final readonly class OnboardOrganizationData
{
    /** @param list<OpeningHourData> $openingHours */
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
        public array $openingHours,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = LegalEntityType::from((string) $data['legal_entity_type']);

        $openingHours = [];
        foreach ((array) ($data['opening_hours'] ?? []) as $index => $hour) {
            $openingHours[] = OpeningHourData::fromArray($hour, $index);
        }

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
            openingHours: $openingHours,
        );
    }
}
