/**
 * Máscaras visuais para campos de formulário (CPF, CNPJ, CEP, telefone).
 * Apenas apresentação — a validação real (dígitos verificadores, formato)
 * é sempre responsabilidade do backend.
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
