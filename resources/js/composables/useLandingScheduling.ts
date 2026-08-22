import { ref } from 'vue';

/**
 * Estado compartilhado entre LandingServicesSection/
 * LandingProfessionalsSection/LandingAvailabilitySearch e
 * LandingSchedulingSection: ao clicar em "Agendar" num serviço, o
 * formulário já abre com esse serviço pré-selecionado; ao clicar num
 * profissional ou num horário concreto da busca de disponibilidade, os
 * campos de observações/data/período preferencial já vêm preenchidos.
 * `selectedProfessionalId` é o vínculo estruturado real (ULID do cadastro
 * operacional) — permite ao profissional encontrar o próprio
 * pré-agendamento depois (ver settings/meus-pre-agendamentos);
 * `selectedProfessionalName` continua existindo separadamente só para
 * compor o texto legível das observações, nunca usado para autorização ou
 * consulta. Módulo-singleton — não precisa de uma store completa (Pinia
 * não está instalado neste projeto) para um valor compartilhado entre
 * seções da mesma página.
 */
const selectedServiceId = ref<number | null>(null);
const selectedProfessionalId = ref<string | null>(null);
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
        selectedProfessionalId,
        selectedProfessionalName,
        preferredDate,
        preferredPeriod,
    };
}
