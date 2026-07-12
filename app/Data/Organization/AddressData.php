<?php

declare(strict_types=1);

namespace App\Data\Organization;

use App\Support\Documents\Document;

final readonly class AddressData
{
    public function __construct(
        public string $postalCode,
        public string $street,
        public string $number,
        public ?string $complement,
        public string $neighborhood,
        public string $city,
        public string $state,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            postalCode: Document::onlyDigits((string) $data['postal_code']),
            street: (string) $data['street'],
            number: (string) $data['number'],
            complement: isset($data['complement']) && $data['complement'] !== '' ? (string) $data['complement'] : null,
            neighborhood: (string) $data['neighborhood'],
            city: (string) $data['city'],
            state: mb_strtoupper((string) $data['state']),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'postal_code' => $this->postalCode,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'country' => 'BR',
        ];
    }
}
