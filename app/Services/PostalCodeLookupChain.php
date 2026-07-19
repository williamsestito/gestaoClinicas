<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Organization\PostalCodeResult;
use App\Support\Documents\Document;
use Illuminate\Support\Facades\Cache;

/**
 * Consulta os provedores de CEP configurados em `config('cep.providers')`,
 * em ordem, parando no primeiro que retornar um endereço válido. O
 * resultado — de qualquer provedor — é cacheado por CEP, não por
 * provedor, para que uma consulta repetida não refaça a cadeia inteira.
 */
final class PostalCodeLookupChain implements PostalCodeLookup
{
    /** @param  array<int, PostalCodeProvider>  $providers */
    public function __construct(private readonly array $providers) {}

    public function lookup(string $postalCode): ?PostalCodeResult
    {
        $digits = Document::onlyDigits($postalCode);

        if (strlen($digits) !== 8) {
            return null;
        }

        $data = Cache::remember(
            "postal-code-lookup:v2:{$digits}",
            now()->addDays((int) config('cep.cache_ttl_days', 30)),
            fn () => $this->fetchFromProviders($digits)?->toArray(),
        );

        return $data ? new PostalCodeResult(...$this->fromArray($data)) : null;
    }

    private function fetchFromProviders(string $digits): ?PostalCodeResult
    {
        foreach ($this->providers as $provider) {
            $result = $provider->fetch($digits);

            if ($result) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array{postalCode: string, street: string, neighborhood: string, city: string, state: string, source: string, ibgeCode: ?string}
     */
    private function fromArray(array $data): array
    {
        return [
            'postalCode' => (string) $data['postal_code'],
            'street' => (string) $data['street'],
            'neighborhood' => (string) $data['neighborhood'],
            'city' => (string) $data['city'],
            'state' => (string) $data['state'],
            'source' => (string) $data['source'],
            'ibgeCode' => $data['ibge_code'] ?? null,
        ];
    }
}
