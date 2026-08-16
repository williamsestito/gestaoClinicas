import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PatientPortalLayout from '@/layouts/patient-portal/PatientPortalLayout.vue';
import ClinicSettingsLayout from '@/layouts/settings/ClinicLayout.vue';
import AccountSettingsLayout from '@/layouts/settings/Layout.vue';
import SiteSettingsLayout from '@/layouts/settings/SiteLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { resolveLayoutKind } from '@/lib/layoutResolution';

const appName = import.meta.env.VITE_APP_NAME || 'Gestão de Clínicas';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (resolveLayoutKind(name)) {
            case 'none':
                return null;
            case 'auth':
                return AuthLayout;
            case 'clinic-site':
                return [AppLayout, ClinicSettingsLayout, SiteSettingsLayout];
            case 'clinic':
                return [AppLayout, ClinicSettingsLayout];
            case 'account':
                return [AppLayout, AccountSettingsLayout];
            case 'patient-portal':
                return PatientPortalLayout;
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
