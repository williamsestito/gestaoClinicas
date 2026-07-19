<?php

declare(strict_types=1);

namespace App\Data\Organization;

final readonly class PostalCodeResult
{
    public function __construct(
        public string $postalCode,
        public string $street,
        public string $neighborhood,
        public string $city,
        public string $state,
        public string $source,
        public ?string $ibgeCode = null,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'postal_code' => $this->postalCode,
            'street' => $this->street,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'source' => $this->source,
            'ibge_code' => $this->ibgeCode,
        ];
    }
}
