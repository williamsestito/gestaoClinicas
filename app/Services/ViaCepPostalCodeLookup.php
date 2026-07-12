<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Organization\PostalCodeResult;
use App\Support\Documents\Document;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Implementação usando a API pública do ViaCEP (https://viacep.com.br).
 * Nunca lança exceção: qualquer falha (timeout, indisponibilidade, CEP
 * inexistente) resulta em `null`, e o formulário permanece editável.
 */
final class ViaCepPostalCodeLookup implements PostalCodeLookup
{
    private const TIMEOUT_SECONDS = 3;

    private const CACHE_TTL_DAYS = 30;

    public function lookup(string $postalCode): ?PostalCodeResult
    {
        $digits = Document::onlyDigits($postalCode);

        if (strlen($digits) !== 8) {
            return null;
        }

        // Cacheia um array simples (nao o objeto de valor): serializar/
        // desserializar objetos via o driver de cache e mais fragil do que
        // um array puro, e nao ha vantagem em cachear o objeto em si.
        $data = Cache::remember(
            "postal-code-lookup:{$digits}",
            now()->addDays(self::CACHE_TTL_DAYS),
            fn () => $this->fetch($digits)?->toArray(),
        );

        return $data ? new PostalCodeResult(...$this->fromArray($data)) : null;
    }

    /**
     * @param  array<string, string>  $data
     * @return array{postalCode: string, street: string, neighborhood: string, city: string, state: string}
     */
    private function fromArray(array $data): array
    {
        return [
            'postalCode' => $data['postal_code'],
            'street' => $data['street'],
            'neighborhood' => $data['neighborhood'],
            'city' => $data['city'],
            'state' => $data['state'],
        ];
    }

    private function fetch(string $digits): ?PostalCodeResult
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
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
        );
    }
}
