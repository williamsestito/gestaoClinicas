<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Organization\PostalCodeResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Implementação usando a API pública da AwesomeAPI CEP
 * (https://cep.awesomeapi.com.br). Nunca lança exceção: qualquer falha
 * (timeout, indisponibilidade, CEP inexistente) resulta em `null`,
 * permitindo que o PostalCodeLookupChain tente o próximo provedor.
 */
final class AwesomeApiPostalCodeLookup implements PostalCodeProvider
{
    public function fetch(string $digits): ?PostalCodeResult
    {
        try {
            $response = Http::timeout((int) config('cep.timeout_seconds', 3))
                ->get("https://cep.awesomeapi.com.br/json/{$digits}");
        } catch (Throwable $exception) {
            Log::warning('Falha ao consultar a AwesomeAPI CEP.', ['exception' => $exception::class]);

            return null;
        }

        // CEP inexistente responde 404 com {"code":"not_found",...}.
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (! is_array($data) || blank($data['address'] ?? null) || blank($data['city'] ?? null)) {
            return null;
        }

        return new PostalCodeResult(
            postalCode: $digits,
            street: $this->streetFromAddress((string) $data['address']),
            neighborhood: (string) ($data['district'] ?? ''),
            city: (string) $data['city'],
            state: (string) ($data['state'] ?? ''),
            source: 'awesomeapi',
            ibgeCode: isset($data['city_ibge']) ? (string) $data['city_ibge'] : null,
        );
    }

    /**
     * A AwesomeAPI retorna o logradouro já com um número de referência
     * anexado (ex.: "Avenida Paulista, 2100") — removemos esse sufixo para
     * manter apenas o nome da rua, já que o número é preenchido pelo
     * usuário separadamente.
     */
    private function streetFromAddress(string $address): string
    {
        return trim(preg_replace('/,\s*\d.*$/', '', $address) ?? $address);
    }
}
