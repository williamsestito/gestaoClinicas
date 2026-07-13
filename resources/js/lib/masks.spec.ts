import { describe, expect, it } from 'vitest';
import { maskCnpj, maskCpf, maskPhone, maskPostalCode } from './masks';

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
});
