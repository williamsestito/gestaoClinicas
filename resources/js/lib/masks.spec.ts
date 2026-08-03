import { describe, expect, it } from 'vitest';
import {
    formatCurrencyBrl,
    formatDateBr,
    formatDateTimeBr,
    maskCnpj,
    maskCpf,
    maskPhone,
    maskPostalCode,
} from './masks';

describe('masks', () => {
    it('formats a CPF as the user types', () => {
        expect(maskCpf('12345678909')).toBe('123.456.789-09');
        expect(maskCpf('123')).toBe('123');
        expect(maskCpf('123456')).toBe('123.456');
    });

    it('formats a CNPJ as the user types', () => {
        expect(maskCnpj('11222333000181')).toBe('11.222.333/0001-81');
    });

    it('formats a postal code (CEP)', () => {
        expect(maskPostalCode('01310100')).toBe('01310-100');
    });

    it('formats mobile and landline phone numbers', () => {
        expect(maskPhone('11999990000')).toBe('(11) 99999-0000');
        expect(maskPhone('1140040000')).toBe('(11) 4004-0000');
    });

    it('ignores non-digit characters when masking', () => {
        expect(maskCpf('123.456.789-09')).toBe('123.456.789-09');
    });

    it('formats a date-only value (YYYY-MM-DD) as dd/mm/aaaa without a day-off-by-one shift', () => {
        expect(formatDateBr('2026-08-10')).toBe('10/08/2026');
    });

    it('formats a full ISO datetime value as dd/mm/aaaa', () => {
        expect(formatDateBr('2026-08-10T23:30:00Z')).toBe(
            new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(
                new Date('2026-08-10T23:30:00Z'),
            ),
        );
    });

    it('formats a UTC instant as dd/mm/aaaa, HH:mm in the given timezone', () => {
        expect(
            formatDateTimeBr('2026-08-10T12:00:00Z', {
                timeZone: 'America/Sao_Paulo',
            }),
        ).toBe('10/08/2026, 09:00');
    });

    it('formats a UTC instant as date-only when withTime is false', () => {
        expect(
            formatDateTimeBr('2026-08-10T12:00:00Z', {
                withTime: false,
                timeZone: 'America/Sao_Paulo',
            }),
        ).toBe('10/08/2026');
    });

    it('defaults formatDateTimeBr to the business timezone (America/Sao_Paulo) when none is given', () => {
        expect(formatDateTimeBr('2026-08-10T12:00:00Z')).toBe(
            '10/08/2026, 09:00',
        );
    });

    it('formats cents as Brazilian currency (R$)', () => {
        // Normaliza o espaço nao separavel que o Intl insere entre "R$" e o
        // valor (codigo 160), para a asserção não depender do ICU do ambiente.
        const normalize = (value: string) =>
            value
                .split('')
                .map((char) => (char.charCodeAt(0) === 160 ? ' ' : char))
                .join('');

        expect(normalize(formatCurrencyBrl(150000))).toBe('R$ 1.500,00');
        expect(normalize(formatCurrencyBrl(0))).toBe('R$ 0,00');
    });
});
