import { ref } from 'vue';

export interface CepLookupResult {
    postal_code: string;
    street: string;
    neighborhood: string;
    city: string;
    state: string;
}

/**
 * Consulta o endpoint interno de CEP (backed pelo ViaCEP). Nunca lança —
 * indisponibilidade ou CEP não encontrado apenas deixam `result` nulo, para
 * que o formulário continue editável manualmente.
 */
export function useCepLookup() {
    const isLoading = ref(false);
    const notFound = ref(false);

    async function lookup(postalCode: string): Promise<CepLookupResult | null> {
        const digits = postalCode.replace(/\D/g, '');

        if (digits.length !== 8) {
            return null;
        }

        isLoading.value = true;
        notFound.value = false;

        try {
            const response = await fetch(`/cep/${digits}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                notFound.value = response.status === 404;

                return null;
            }

            return (await response.json()) as CepLookupResult;
        } catch {
            return null;
        } finally {
            isLoading.value = false;
        }
    }

    return { lookup, isLoading, notFound };
}
