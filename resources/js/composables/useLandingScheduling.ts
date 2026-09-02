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
/**
 * Unidade e serviço REAIS (cadastro operacional, ULID) e o horário exato
 * (string local `YYYY-MM-DDTHH:mm:ss`, mesmo formato de `selectSlot()`)
 * escolhidos na busca de disponibilidade — permitem ao pré-agendamento
 * carregar tudo que `App\Actions\Organization\CreateAppointmentAction`
 * precisa, sem exigir reescolher nada ao converter (ver "Meus
 * pré-agendamentos"). `preferredServiceId` nunca é o mesmo espaço de id de
 * `selectedServiceId` acima (que é o `SiteService` do formulário manual).
 * Ficam `null` quando o lead nunca passou por um horário específico da
 * busca — só pelo formulário manual.
 */
const preferredUnitId = ref<string | null>(null);
const preferredServiceId = ref<string | null>(null);
const preferredStartsAt = ref<string | null>(null);

export function useLandingScheduling() {
    return {
        selectedServiceId,
        selectedProfessionalId,
        selectedProfessionalName,
        preferredDate,
        preferredPeriod,
        preferredUnitId,
        preferredServiceId,
        preferredStartsAt,
    };
}
