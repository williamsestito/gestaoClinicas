/**
 * Monta a URL de conversa do WhatsApp (wa.me) a partir de um número
 * cadastrado livremente pelo administrador (com ou sem DDI, com ou sem
 * máscara). Evita duplicar o código do país "55" quando o número já foi
 * digitado com ele.
 */
export function buildWhatsAppUrl(rawPhone: string | null | undefined): string | null {
    if (!rawPhone) {
        return null;
    }

    const digits = rawPhone.replace(/\D/g, '');

    if (!digits) {
        return null;
    }

    // Celular BR: DDD (2) + 9 dígitos = 11; com o DDI 55 já incluído, 13.
    const withCountryCode =
        digits.length === 13 && digits.startsWith('55')
            ? digits
            : `55${digits}`;

    return `https://wa.me/${withCountryCode}`;
}
