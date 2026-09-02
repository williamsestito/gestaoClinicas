import { describe, expect, it, vi } from 'vitest';

vi.mock('@/routes', () => ({
    dashboard: () => ({ url: '/dashboard', method: 'get' }),
}));
vi.mock('@/routes/settings/organization', () => ({
    edit: () => ({ url: '/settings/organization', method: 'get' }),
}));
vi.mock('@/routes/settings/units', () => ({
    index: () => ({ url: '/settings/units', method: 'get' }),
}));
vi.mock('@/routes/settings/legal-entities', () => ({
    index: () => ({ url: '/settings/legal-entities', method: 'get' }),
}));
vi.mock('@/routes/settings/users', () => ({
    index: () => ({ url: '/settings/users', method: 'get' }),
}));
vi.mock('@/routes/settings/roles', () => ({
    index: () => ({ url: '/settings/roles', method: 'get' }),
}));

const { buildNavGroups } = await import('./navigation');

describe('buildNavGroups', () => {
    it('hides every group when the user has no permissions at all', () => {
        expect(buildNavGroups([])).toEqual([]);
    });

    it('only shows items whose permission is present, dropping empty groups', () => {
        const groups = buildNavGroups(['dashboard.view', 'units.view']);

        expect(groups.map((g) => g.title)).toEqual([
            'Visão geral',
            'Gestão da clínica',
        ]);
        expect(groups[1].items.map((i) => i.title)).toEqual(['Unidades']);
    });

    it('shows every configured item when all permissions are granted', () => {
        const groups = buildNavGroups([
            'dashboard.view',
            'organization.view',
            'units.view',
            'legal-entities.view',
            'users.view',
            'roles.view',
        ]);

        const allTitles = groups.flatMap((g) => g.items.map((i) => i.title));
        expect(allTitles).toEqual([
            'Dashboard',
            'Dados da clínica',
            'Unidades',
            'Entidades legais',
            'Usuários',
            'Perfis e permissões',
        ]);
    });

    it('never uses href="#" for any configured item', () => {
        const groups = buildNavGroups([
            'dashboard.view',
            'organization.view',
            'units.view',
            'legal-entities.view',
            'users.view',
            'roles.view',
        ]);

        for (const group of groups) {
            for (const item of group.items) {
                expect(item.href).not.toBe('#');
            }
        }
    });
});
