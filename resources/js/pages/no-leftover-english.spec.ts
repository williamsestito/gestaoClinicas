import { describe, expect, it } from 'vitest';

/**
 * Varre as páginas principais em busca de textos em inglês herdados do
 * starter kit que não deveriam mais aparecer na interface (pt-BR é o
 * idioma padrão da aplicação — ver docs/architecture/localization.md).
 * Não verifica identificadores técnicos (nomes de props, classes CSS,
 * rotas), só o HTML/texto visível dentro de cada <template>.
 */

const FORBIDDEN_PHRASES = [
    'Log in',
    'Sign up',
    "Don't have an account",
    'Forgot your password',
    'Remember me',
    'Email address',
    'Confirm password',
    'New password',
    'Delete account',
    'Two Factor Authentication',
    'Recovery Codes',
    'Manage your profile',
    'Update your',
    'Save',
    'Cancel',
];

const pageModules = import.meta.glob('./**/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
}) as Record<string, string>;

describe('no leftover English text on main pages', () => {
    const entries = Object.entries(pageModules);

    it('found at least one page to scan', () => {
        expect(entries.length).toBeGreaterThan(0);
    });

    it.each(entries)('%s has no forbidden English phrases', (path, content) => {
        const templateMatch = content.match(/<template>([\s\S]*)<\/template>/);
        const template = templateMatch ? templateMatch[1] : '';

        const found = FORBIDDEN_PHRASES.filter((phrase) => {
            const pattern = new RegExp(
                `\\b${phrase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`,
            );

            return pattern.test(template);
        });

        expect(found).toEqual([]);
    });
});
