import { ref } from 'vue';

/**
 * Estado compartilhado entre LandingServicesSection/
 * LandingProfessionalsSection/LandingAvailabilitySearch e
 * LandingSchedulingSection: ao clicar em "Agendar" num serviço, o
 * formulário já abre com esse serviço pré-selecionado; ao clicar num
 * profissional ou num horário concreto da busca de disponibilidade, os
 * campos de observações/data/período preferencial já vêm preenchidos (não
 * existe seleção real de profissional/data/hora no formulário — só o
 * serviço tem coluna própria em `appointment_requests`; os demais dados só
 * viram texto/preferência, e a solicitação real só é enviada quando a
 * pessoa preenche os dados pessoais e confirma). Módulo-singleton — não
 * precisa de uma store completa (Pinia não está instalado neste projeto)
 * para um valor compartilhado entre seções da mesma página.
 */
const selectedServiceId = ref<number | null>(null);
const selectedProfessionalName = ref<string | null>(null);
/**
 * Data/período sugeridos pela busca de disponibilidade ao clicar num
 * horário — campos estruturados (não texto livre), então sempre refletem a
 * última escolha feita na busca, sem a mesma proteção contra sobrescrita
 * usada em `selectedProfessionalName`.
 */
const preferredDate = ref<string | null>(null);
const preferredPeriod = ref<string | null>(null);

export function useLandingScheduling() {
    return {
        selectedServiceId,
        selectedProfessionalName,
        preferredDate,
        preferredPeriod,
    };
}
