/**
 * Máscaras e formatadores visuais (CPF, CNPJ, CEP, telefone, data, hora,
 * moeda). Apenas apresentação — a validação e a normalização reais são
 * sempre responsabilidade do backend.
 */

function onlyDigits(value: string): string {
    return value.replace(/\D/g, '');
}

export function maskCpf(value: string): string {
    const digits = onlyDigits(value).slice(0, 11);

    return digits
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

export function maskCnpj(value: string): string {
    const digits = onlyDigits(value).slice(0, 14);

    return digits
        .replace(/(\d{2})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1/$2')
        .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
}

export function maskCpfCnpj(
    value: string,
    type: 'individual' | 'company',
): string {
    return type === 'individual' ? maskCpf(value) : maskCnpj(value);
}

export function maskPostalCode(value: string): string {
    const digits = onlyDigits(value).slice(0, 8);

    return digits.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
}

export function maskPhone(value: string): string {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length <= 10) {
        return digits
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
    }

    return digits
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
}

/**
 * Formata uma data (YYYY-MM-DD ou ISO completo) como dd/mm/aaaa.
 * Para valores somente-data, monta meia-noite local explicitamente para
 * evitar o deslocamento de um dia que ocorreria ao interpretar
 * "YYYY-MM-DD" como meia-noite UTC em fusos negativos.
 */
export function formatDateBr(value: string): string {
    const date = value.includes('T')
        ? new Date(value)
        : new Date(`${value}T00:00:00`);

    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(
        date,
    );
}

/**
 * Formata um instante UTC (ex.: created_at/updated_at) como dd/mm/aaaa,
 * opcionalmente com HH:mm, no fuso informado. Sem fuso explícito por
 * organização/unidade disponível na tela, usa o padrão do negócio
 * (America/Sao_Paulo — ver config('business.default_timezone')).
 */
export function formatDateTimeBr(
    value: string,
    options: { withTime?: boolean; timeZone?: string } = {},
): string {
    const { withTime = true, timeZone = 'America/Sao_Paulo' } = options;

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        ...(withTime ? { timeStyle: 'short' as const } : {}),
        timeZone,
    }).format(new Date(value));
}

/** Formata um valor em centavos como moeda brasileira (R$ 0,00). */
export function formatCurrencyBrl(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}
