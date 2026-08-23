/**
 * Prefixos de páginas de configuração da IDENTIDADE da clínica (dados da
 * clínica, entidades legais, unidades, papéis, usuários, site público) —
 * usam o layout com o menu "Configurações da clínica", nunca o menu
 * "Minha conta" (perfil, segurança, aparência), que é exclusivamente
 * pessoal.
 */
export const CLINIC_SETTINGS_PAGES = [
    'settings/Organization',
    'settings/legal-entities/',
    'settings/units/',
    'settings/roles/',
    'settings/users/',
    'settings/site/',
    'settings/seo/',
    'settings/audit/',
    'settings/modules/',
];

/**
 * Páginas de dado operacional da clínica (profissionais, especialidades,
 * serviços, recursos, pacientes, agenda) vivem sob o prefixo `settings/`
 * por convenção de rotas, mas não são nem "Configurações da clínica" (não
 * aparecem no menu de identidade da clínica) nem pessoais ("Minha conta")
 * — usam o layout padrão, sem sub-menu, para não ficarem presas ao teto
 * estreito (`max-w-2xl`) do layout pessoal nem exibirem um sub-menu de
 * clínica que não as contém.
 */
export const PLAIN_APP_SETTINGS_PAGES = [
    'settings/professionals/',
    'settings/specialties/',
    'settings/services/',
    'settings/resources/',
    'settings/patients/',
    'settings/appointments/',
    // Tabela (não formulário simples como settings/my-schedule/) — precisa
    // do mesmo teto largo de settings/patients/, não do max-w-2xl pessoal.
    'settings/my-patients/',
    'settings/my-appointment-requests/',
];

/**
 * Páginas de convidado do portal do paciente (login/cadastro/senha) usam o
 * mesmo layout "auth" de staff (AuthSimpleLayout) — visual neutro o
 * bastante para ambos. As demais páginas de "patient-portal/" (autenticadas
 * pelo guard "patient") usam um layout próprio, sem sidebar/tenant switcher.
 */
export const PATIENT_PORTAL_GUEST_PAGES = [
    'patient-portal/Register',
    'patient-portal/ForgotPassword',
    'patient-portal/ResetPassword',
];

export type LayoutKind =
    | 'none'
    | 'auth'
    | 'clinic-site'
    | 'clinic'
    | 'account'
    | 'app'
    | 'patient-portal';

/**
 * Decide qual grupo de layout uma página Inertia deve usar, a partir do
 * nome do componente. Extraído do bootstrap (`app.ts`) para ser testável —
 * um prefixo esquecido aqui faz a página cair silenciosamente no layout
 * pessoal "Minha conta", com sidebar errada e largura de conteúdo estreita
 * (`max-w-2xl`), sem nenhum erro visível.
 */
export function resolveLayoutKind(name: string): LayoutKind {
    if (name === 'Welcome') {
        return 'none';
    }

    if (name.startsWith('auth/')) {
        return 'auth';
    }

    if (PATIENT_PORTAL_GUEST_PAGES.some((page) => name === page)) {
        return 'auth';
    }

    if (name.startsWith('patient-portal/')) {
        return 'patient-portal';
    }

    // Sub-área "Site da clínica" tem navegação própria (seções, benefícios,
    // serviços, equipe, galeria, depoimentos, FAQ, agendamentos) — precisa
    // vir antes do case geral de settings de clínica abaixo.
    if (name.startsWith('settings/site/')) {
        return 'clinic-site';
    }

    if (CLINIC_SETTINGS_PAGES.some((page) => name.startsWith(page))) {
        return 'clinic';
    }

    if (PLAIN_APP_SETTINGS_PAGES.some((page) => name.startsWith(page))) {
        return 'app';
    }

    if (name.startsWith('settings/')) {
        return 'account';
    }

    return 'app';
}
