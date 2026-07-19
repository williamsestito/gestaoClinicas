<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Organization\PostalCodeResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Implementação usando a API pública do ViaCEP (https://viacep.com.br).
 * Nunca lança exceção: qualquer falha (timeout, indisponibilidade, CEP
 * inexistente) resulta em `null`, permitindo que o PostalCodeLookupChain
 * tente o próximo provedor.
 */
final class ViaCepPostalCodeLookup implements PostalCodeProvider
{
    public function fetch(string $digits): ?PostalCodeResult
    {
        try {
            $response = Http::timeout((int) config('cep.timeout_seconds', 3))
                ->get("https://viacep.com.br/ws/{$digits}/json/");
        } catch (Throwable $exception) {
            Log::warning('Falha ao consultar o ViaCEP.', ['exception' => $exception::class]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        // A API responde 200 com {"erro": true} para CEPs inexistentes.
        if (! is_array($data) || ($data['erro'] ?? false) === true) {
            return null;
        }

        if (blank($data['logradouro'] ?? null) && blank($data['localidade'] ?? null)) {
            return null;
        }

        return new PostalCodeResult(
            postalCode: $digits,
            street: (string) ($data['logradouro'] ?? ''),
            neighborhood: (string) ($data['bairro'] ?? ''),
            city: (string) ($data['localidade'] ?? ''),
            state: (string) ($data['uf'] ?? ''),
            source: 'viacep',
        );
    }
}
