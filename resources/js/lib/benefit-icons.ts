import {
    Award,
    BadgeCheck,
    Clock,
    GraduationCap,
    Heart,
    HeartHandshake,
    Leaf,
    Shield,
    ShieldCheck,
    Smile,
    Sparkles,
    Stethoscope,
    ThumbsUp,
    Users,
} from '@lucide/vue';
import type { FunctionalComponent } from 'vue';

export type BenefitIconOption = {
    /** Identificador salvo em `site_benefits.icon` — nunca muda depois de
     *  publicado (compatibilidade com registros existentes). */
    key: string;
    label: string;
    icon: FunctionalComponent;
};

/**
 * Conjunto curado (não um `import *` da biblioteca inteira, que impediria
 * o tree-shaking e inflaria o chunk) — cobre os ícones mais comuns para
 * diferenciais de clínicas de saúde/estética. Fonte única, compartilhada
 * pelo seletor visual do admin (IconPicker.vue) e pela renderização
 * pública (LandingBenefitsSection.vue), para nunca haver divergência
 * entre "o que pode ser escolhido" e "o que sabe ser exibido".
 */
export const BENEFIT_ICONS: BenefitIconOption[] = [
    {
        key: 'heart-handshake',
        label: 'Cuidado / acolhimento',
        icon: HeartHandshake,
    },
    { key: 'heart', label: 'Saúde / bem-estar', icon: Heart },
    { key: 'heart-pulse', label: 'Vitalidade', icon: Heart },
    { key: 'shield-check', label: 'Segurança verificada', icon: ShieldCheck },
    { key: 'shield', label: 'Segurança', icon: Shield },
    { key: 'sparkles', label: 'Qualidade / destaque', icon: Sparkles },
    {
        key: 'graduation-cap',
        label: 'Qualificação profissional',
        icon: GraduationCap,
    },
    { key: 'stethoscope', label: 'Atendimento clínico', icon: Stethoscope },
    { key: 'clock', label: 'Agilidade / horário', icon: Clock },
    { key: 'users', label: 'Equipe / pessoas', icon: Users },
    { key: 'award', label: 'Reconhecimento', icon: Award },
    { key: 'badge-check', label: 'Confiabilidade', icon: BadgeCheck },
    { key: 'thumbs-up', label: 'Aprovação', icon: ThumbsUp },
    { key: 'smile', label: 'Satisfação', icon: Smile },
    { key: 'leaf', label: 'Natural / sustentável', icon: Leaf },
];

const DEFAULT_ICON = BENEFIT_ICONS[0];

const ICONS_BY_KEY: Record<string, BenefitIconOption> = Object.fromEntries(
    BENEFIT_ICONS.map((option) => [option.key, option]),
);

/** Resolve um ícone salvo para exibição — valor desconhecido cai no padrão. */
export function benefitIconFor(key: string | null): FunctionalComponent {
    if (!key) {
        return DEFAULT_ICON.icon;
    }

    return (ICONS_BY_KEY[key] ?? DEFAULT_ICON).icon;
}

/** Nome legível do ícone salvo, para exibição no seletor. */
export function benefitIconLabel(key: string | null): string {
    if (!key || !ICONS_BY_KEY[key]) {
        return 'Escolher ícone';
    }

    return ICONS_BY_KEY[key].label;
}
