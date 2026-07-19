import { ref } from 'vue';

export type CepLookupStatus =
    'idle' | 'loading' | 'success' | 'not-found' | 'error';

export interface CepLookupResult {
    postal_code: string;
    street: string;
    neighborhood: string;
    city: string;
    state: string;
    source: 'awesomeapi' | 'apicep' | 'viacep';
    ibge_code?: string;
}

/**
 * Consulta o endpoint interno de CEP (com fallback entre AwesomeAPI CEP,
 * API CEP e ViaCEP, resolvido no backend). Nunca lança — indisponibilidade
 * ou CEP não encontrado apenas mudam `status`, para que o formulário
 * continue editável manualmente.
 */
export function useCepLookup() {
    const status = ref<CepLookupStatus>('idle');
    const lastQueriedDigits = ref<string | null>(null);
    const lastResult = ref<CepLookupResult | null>(null);

    async function lookup(
        postalCode: string,
        options: { force?: boolean } = {},
    ): Promise<CepLookupResult | null> {
        const digits = postalCode.replace(/\D/g, '');

        if (digits.length !== 8) {
            return null;
        }

        // Evita repetir a mesma consulta para o mesmo CEP durante a edição.
        if (!options.force && digits === lastQueriedDigits.value) {
            return lastResult.value;
        }

        status.value = 'loading';

        try {
            const response = await fetch(`/cep/${digits}`, {
                headers: { Accept: 'application/json' },
            });

            lastQueriedDigits.value = digits;

            if (!response.ok) {
                lastResult.value = null;
                status.value = response.status === 404 ? 'not-found' : 'error';

                return null;
            }

            const result = (await response.json()) as CepLookupResult;
            lastResult.value = result;
            status.value = 'success';

            return result;
        } catch {
            lastQueriedDigits.value = digits;
            lastResult.value = null;
            status.value = 'error';

            return null;
        }
    }

    return { lookup, status };
}
