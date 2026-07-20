import { ref } from 'vue';

/**
 * Estado compartilhado entre LandingServicesSection e
 * LandingSchedulingSection: ao clicar em "Agendar" num serviço, o
 * formulário de agendamento já abre com esse serviço pré-selecionado.
 * Módulo-singleton — não precisa de uma store completa (Pinia não está
 * instalado neste projeto) para um único valor compartilhado entre duas
 * seções da mesma página.
 */
const selectedServiceId = ref<number | null>(null);

export function useLandingScheduling() {
    return { selectedServiceId };
}
