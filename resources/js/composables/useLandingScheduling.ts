import { ref } from 'vue';

/**
 * Estado compartilhado entre LandingServicesSection/
 * LandingProfessionalsSection e LandingSchedulingSection: ao clicar em
 * "Agendar" num serviço, o formulário já abre com esse serviço
 * pré-selecionado; ao clicar num profissional, o campo de observações já
 * vem preenchido com o nome dele (não existe seleção real de profissional
 * no formulário — só o serviço tem essa coluna em `appointment_requests`).
 * Módulo-singleton — não precisa de uma store completa (Pinia não está
 * instalado neste projeto) para um valor compartilhado entre seções da
 * mesma página.
 */
const selectedServiceId = ref<number | null>(null);
const selectedProfessionalName = ref<string | null>(null);

export function useLandingScheduling() {
    return { selectedServiceId, selectedProfessionalName };
}
