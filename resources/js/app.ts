import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import ClinicSettingsLayout from '@/layouts/settings/ClinicLayout.vue';
import AccountSettingsLayout from '@/layouts/settings/Layout.vue';
import SiteSettingsLayout from '@/layouts/settings/SiteLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Gestão de Clínicas';

// Páginas de configuração da clínica (dados da clínica, entidades legais,
// unidades) usam um menu próprio — nunca o menu "Minha conta" (perfil,
// segurança, aparência), que é exclusivamente pessoal.
const clinicSettingsPages = [
    'settings/Organization',
    'settings/legal-entities/',
    'settings/units/',
    'settings/roles/',
    'settings/users/',
    'settings/site/',
    'settings/seo/',
    'settings/audit/',
];

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            // Sub-área "Site da clínica" tem navegação própria (seções,
            // benefícios, serviços, equipe, galeria, depoimentos, FAQ,
            // agendamentos) — precisa vir antes do case geral abaixo.
            case name.startsWith('settings/site/'):
                return [AppLayout, ClinicSettingsLayout, SiteSettingsLayout];
            case clinicSettingsPages.some((page) => name.startsWith(page)):
                return [AppLayout, ClinicSettingsLayout];
            case name.startsWith('settings/'):
                return [AppLayout, AccountSettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
