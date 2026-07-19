<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Organization\PostalCodeResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Implementação usando a API CEP (https://apicep.com) — um serviço de
 * arquivos estáticos, não uma API dinâmica: exige o CEP formatado com
 * hífen (ex.: "01310-930"), diferente dos outros dois provedores, que
 * aceitam apenas dígitos. Nunca lança exceção: qualquer falha resulta em
 * `null`, permitindo que o PostalCodeLookupChain tente o próximo provedor.
 */
final class ApiCepPostalCodeLookup implements PostalCodeProvider
{
    public function fetch(string $digits): ?PostalCodeResult
    {
        $formatted = substr($digits, 0, 5).'-'.substr($digits, 5);

        try {
            $response = Http::timeout((int) config('cep.timeout_seconds', 3))
                ->get("https://cdn.apicep.com/file/apicep/{$formatted}.json");
        } catch (Throwable $exception) {
            Log::warning('Falha ao consultar a API CEP.', ['exception' => $exception::class]);

            return null;
        }

        // CEP inexistente responde 404 com {"code":"not_found",...}.
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (! is_array($data) || ($data['ok'] ?? false) !== true || blank($data['address'] ?? null)) {
            return null;
        }

        return new PostalCodeResult(
            postalCode: $digits,
            street: $this->streetFromAddress((string) $data['address']),
            neighborhood: (string) ($data['district'] ?? ''),
            city: (string) ($data['city'] ?? ''),
            state: (string) ($data['state'] ?? ''),
            source: 'apicep',
        );
    }

    /**
     * A API CEP retorna o logradouro já com um número de referência
     * anexado (ex.: "Avenida Paulista, 2100") — removemos esse sufixo para
     * manter apenas o nome da rua, já que o número é preenchido pelo
     * usuário separadamente.
     */
    private function streetFromAddress(string $address): string
    {
        return trim(preg_replace('/,\s*\d.*$/', '', $address) ?? $address);
    }
}
