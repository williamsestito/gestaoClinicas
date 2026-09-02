import type { LandingSectionType } from '@/types/site';

/**
 * Rótulos de navegação das seções da landing — fonte única compartilhada
 * entre a navbar e o rodapé (evita duas listas divergentes apontando para
 * as mesmas âncoras). Só as seções com âncora de navegação real entram
 * aqui; seções puramente visuais (banner, indicadores, diferenciais,
 * convênios, CTA) não têm entrada.
 */
export const LANDING_NAV_LABELS: Partial<Record<LandingSectionType, string>> = {
    hero: 'Início',
    about: 'Sobre',
    services: 'Serviços',
    professionals: 'Profissionais',
    gallery: 'Estrutura',
    testimonials: 'Depoimentos',
    faq: 'Dúvidas',
    contact: 'Contato',
};
