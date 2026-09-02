import { describe, expect, it } from 'vitest';
import { resolveLayoutKind } from './layoutResolution';

describe('resolveLayoutKind', () => {
    it('renders the Welcome page without any layout', () => {
        expect(resolveLayoutKind('Welcome')).toBe('none');
    });

    it('uses the auth layout for every auth/* page', () => {
        expect(resolveLayoutKind('auth/Login')).toBe('auth');
        expect(resolveLayoutKind('auth/Register')).toBe('auth');
    });

    it('uses the clinic-site layout for the public site sub-area, not the generic clinic one', () => {
        expect(resolveLayoutKind('settings/site/Index')).toBe('clinic-site');
        expect(resolveLayoutKind('settings/site/services/Index')).toBe(
            'clinic-site',
        );
    });

    it.each([
        'settings/Organization',
        'settings/legal-entities/Index',
        'settings/units/Index',
        'settings/roles/Index',
        'settings/users/Index',
        'settings/seo/Index',
        'settings/audit/Index',
        'settings/modules/Index',
    ])(
        'uses the clinic layout for %s (already covered before this fix)',
        (name) => {
            expect(resolveLayoutKind(name)).toBe('clinic');
        },
    );

    // Regressão: profissionais, especialidades, serviços, recursos,
    // pacientes e agenda caíam silenciosamente no layout pessoal "Minha
    // conta" (sidebar errada, conteúdo limitado a max-w-2xl, exigindo
    // scroll horizontal em tabelas) — não são "Configurações da clínica"
    // (não têm item no sub-menu de identidade da clínica), então usam o
    // layout padrão.
    it.each([
        'settings/professionals/Index',
        'settings/professionals/Agendas',
        'settings/professionals/Availability',
        'settings/professionals/TimeBlocks',
        'settings/specialties/Index',
        'settings/services/Index',
        'settings/resources/Index',
        'settings/patients/Index',
        'settings/appointments/Index',
        'settings/my-patients/Index',
        'settings/my-appointment-requests/Index',
        'settings/products/Index',
        'settings/products/Create',
        'settings/sales/Index',
        'settings/sales/Create',
        'settings/sales/Show',
    ])(
        'uses the plain app layout for %s — neither the clinic-identity nor the personal account layout',
        (name) => {
            expect(resolveLayoutKind(name)).toBe('app');
        },
    );

    it('still uses the personal account layout for genuinely personal settings pages', () => {
        expect(resolveLayoutKind('settings/Profile')).toBe('account');
        expect(resolveLayoutKind('settings/Password')).toBe('account');
        expect(resolveLayoutKind('settings/my-schedule/Show')).toBe('account');
    });

    it('falls back to the plain app layout for everything else', () => {
        expect(resolveLayoutKind('Dashboard')).toBe('app');
        expect(resolveLayoutKind('Gallery')).toBe('app');
    });

    it('uses the auth layout for patient-portal guest pages (register/password) — login itself lives at the general /login', () => {
        expect(resolveLayoutKind('patient-portal/Register')).toBe('auth');
        expect(resolveLayoutKind('patient-portal/ForgotPassword')).toBe('auth');
        expect(resolveLayoutKind('patient-portal/ResetPassword')).toBe('auth');
    });

    it('uses the patient-portal layout for authenticated portal pages', () => {
        expect(resolveLayoutKind('patient-portal/Dashboard')).toBe(
            'patient-portal',
        );
        expect(resolveLayoutKind('patient-portal/patients/Edit')).toBe(
            'patient-portal',
        );
        expect(resolveLayoutKind('patient-portal/dependents/Create')).toBe(
            'patient-portal',
        );
    });
});
