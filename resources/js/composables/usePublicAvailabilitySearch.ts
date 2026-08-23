import { computed, ref } from 'vue';
import {
    dates as datesRoute,
    professionals as professionalsRoute,
    services as servicesRoute,
    specialties as specialtiesRoute,
    times as timesRoute,
    units as unitsRoute,
} from '@/routes/public-availability';

export type AvailabilityUnit = {
    id: string;
    name: string;
    neighborhood: string | null;
    city: string | null;
    state: string | null;
};

export type AvailabilitySpecialty = { id: string; name: string };

export type AvailabilityService = {
    id: string;
    name: string;
    description: string | null;
    default_duration_minutes: number;
};

export type AvailabilityProfessional = {
    id: string;
    name: string;
    photo_url: string | null;
};

export type AvailabilityDate = { date: string; is_available: boolean };

export type AvailabilityTime = {
    time: string;
    professional_id: string;
    professional_name: string;
    unit_name: string;
    service_name: string;
    duration_minutes: number;
};

const ANY_PROFESSIONAL = 'any';

export type LoadingKey =
    'units' | 'specialties' | 'services' | 'professionals' | 'dates' | 'times';

/**
 * Estado e chamadas da busca pública de disponibilidade — unidade →
 * especialidade → serviço → profissional (opcional) → data → horário.
 * Nenhuma etapa persiste nada; apenas consulta os endpoints somente
 * leitura em routes/public-site.php. Trocar uma etapa anterior sempre
 * limpa as seguintes, para nunca manter uma seleção incompatível.
 */
export function usePublicAvailabilitySearch() {
    const units = ref<AvailabilityUnit[]>([]);
    const specialties = ref<AvailabilitySpecialty[]>([]);
    const services = ref<AvailabilityService[]>([]);
    const professionals = ref<AvailabilityProfessional[]>([]);
    const dates = ref<AvailabilityDate[]>([]);
    const times = ref<AvailabilityTime[]>([]);

    const selectedUnitId = ref<string | null>(null);
    const selectedSpecialtyId = ref<string | null>(null);
    const selectedServiceId = ref<string | null>(null);
    const selectedProfessionalId = ref<string | null>(null);
    const selectedDate = ref<string | null>(null);
    const currentMonth = ref(new Date().toISOString().slice(0, 7));

    // Um Set (não um único valor) porque etapas seguidas disparam buscas em
    // paralelo (ex.: escolher a unidade carrega especialidades e serviços
    // ao mesmo tempo) — um ref único era sobrescrito pela última chamada
    // síncrona e zerado pela primeira Promise a resolver, mesmo com a outra
    // ainda em andamento, fazendo a tela parecer "pronta" (sem spinner, sem
    // dado) enquanto uma busca mais lenta ainda respondia. Cada chave só sai
    // do Set quando a própria busca dela termina.
    const loadingKeys = ref<Set<LoadingKey>>(new Set());
    const error = ref<string | null>(null);

    function isLoading(key: LoadingKey): boolean {
        return loadingKeys.value.has(key);
    }

    function startLoading(key: LoadingKey): void {
        loadingKeys.value.add(key);
    }

    function stopLoading(key: LoadingKey): void {
        loadingKeys.value.delete(key);
    }

    async function fetchJson<T>(url: string): Promise<T[]> {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('request-failed');
        }

        const body = (await response.json()) as { data: T[] };

        return body.data;
    }

    async function loadUnits() {
        startLoading('units');
        error.value = null;

        try {
            units.value = await fetchJson<AvailabilityUnit>(unitsRoute().url);
        } catch {
            error.value =
                'Não foi possível carregar as unidades. Tente novamente.';
        } finally {
            stopLoading('units');
        }
    }

    async function loadSpecialties() {
        if (!selectedUnitId.value) {
            return;
        }

        startLoading('specialties');
        error.value = null;

        try {
            specialties.value = await fetchJson<AvailabilitySpecialty>(
                specialtiesRoute({ query: { unit_id: selectedUnitId.value } })
                    .url,
            );
        } catch {
            error.value =
                'Não foi possível carregar as especialidades. Tente novamente.';
        } finally {
            stopLoading('specialties');
        }
    }

    async function loadServices() {
        if (!selectedUnitId.value) {
            return;
        }

        startLoading('services');
        error.value = null;

        try {
            services.value = await fetchJson<AvailabilityService>(
                servicesRoute({
                    query: {
                        unit_id: selectedUnitId.value,
                        ...(selectedSpecialtyId.value
                            ? { specialty_id: selectedSpecialtyId.value }
                            : {}),
                    },
                }).url,
            );
        } catch {
            error.value =
                'Não foi possível carregar os serviços. Tente novamente.';
        } finally {
            stopLoading('services');
        }
    }

    async function loadProfessionals() {
        if (!selectedUnitId.value || !selectedServiceId.value) {
            return;
        }

        startLoading('professionals');
        error.value = null;

        try {
            professionals.value = await fetchJson<AvailabilityProfessional>(
                professionalsRoute({
                    query: {
                        unit_id: selectedUnitId.value,
                        service_id: selectedServiceId.value,
                        ...(selectedSpecialtyId.value
                            ? { specialty_id: selectedSpecialtyId.value }
                            : {}),
                    },
                }).url,
            );
        } catch {
            error.value =
                'Não foi possível carregar os profissionais. Tente novamente.';
        } finally {
            stopLoading('professionals');
        }
    }

    async function loadDates() {
        if (!selectedUnitId.value || !selectedServiceId.value) {
            return;
        }

        startLoading('dates');
        error.value = null;

        try {
            dates.value = await fetchJson<AvailabilityDate>(
                datesRoute({
                    query: {
                        unit_id: selectedUnitId.value,
                        service_id: selectedServiceId.value,
                        month: currentMonth.value,
                        ...(selectedSpecialtyId.value
                            ? { specialty_id: selectedSpecialtyId.value }
                            : {}),
                        ...(selectedProfessionalId.value &&
                        selectedProfessionalId.value !== ANY_PROFESSIONAL
                            ? { professional_id: selectedProfessionalId.value }
                            : {}),
                    },
                }).url,
            );
        } catch {
            error.value =
                'Não foi possível carregar o calendário. Tente novamente.';
        } finally {
            stopLoading('dates');
        }
    }

    async function loadTimes() {
        if (
            !selectedUnitId.value ||
            !selectedServiceId.value ||
            !selectedDate.value
        ) {
            return;
        }

        startLoading('times');
        error.value = null;

        try {
            times.value = await fetchJson<AvailabilityTime>(
                timesRoute({
                    query: {
                        unit_id: selectedUnitId.value,
                        service_id: selectedServiceId.value,
                        date: selectedDate.value,
                        ...(selectedSpecialtyId.value
                            ? { specialty_id: selectedSpecialtyId.value }
                            : {}),
                        ...(selectedProfessionalId.value &&
                        selectedProfessionalId.value !== ANY_PROFESSIONAL
                            ? { professional_id: selectedProfessionalId.value }
                            : {}),
                    },
                }).url,
            );
        } catch {
            error.value =
                'Não foi possível carregar os horários. Tente novamente.';
        } finally {
            stopLoading('times');
        }
    }

    function selectUnit(unitId: string) {
        selectedUnitId.value = unitId;
        selectedSpecialtyId.value = null;
        selectedServiceId.value = null;
        selectedProfessionalId.value = null;
        selectedDate.value = null;
        specialties.value = [];
        services.value = [];
        professionals.value = [];
        dates.value = [];
        times.value = [];
        void loadSpecialties();
        void loadServices();
    }

    function selectSpecialty(specialtyId: string | null) {
        selectedSpecialtyId.value = specialtyId;
        selectedServiceId.value = null;
        selectedProfessionalId.value = null;
        selectedDate.value = null;
        services.value = [];
        professionals.value = [];
        dates.value = [];
        times.value = [];
        void loadServices();
    }

    function selectService(serviceId: string) {
        selectedServiceId.value = serviceId;
        selectedProfessionalId.value = null;
        selectedDate.value = null;
        professionals.value = [];
        dates.value = [];
        times.value = [];
        void loadProfessionals();
        void loadDates();
    }

    function selectProfessional(professionalId: string) {
        selectedProfessionalId.value = professionalId;
        selectedDate.value = null;
        times.value = [];
        void loadDates();
    }

    function selectDate(date: string) {
        selectedDate.value = date;
        void loadTimes();
    }

    function changeMonth(month: string) {
        currentMonth.value = month;
        void loadDates();
    }

    /**
     * Volta ao estado inicial (só `units` continua carregado — a lista de
     * unidades não muda por causa de um envio de solicitação, recarregar é
     * desperdício). Usado depois que a solicitação manual é enviada com
     * sucesso: sem isso, unidade/especialidade/serviço/profissional/data
     * escolhidos aqui ficavam visíveis mesmo com o formulário abaixo já
     * limpo, incoerente com a mensagem de sucesso.
     */
    function reset() {
        selectedUnitId.value = null;
        selectedSpecialtyId.value = null;
        selectedServiceId.value = null;
        selectedProfessionalId.value = null;
        selectedDate.value = null;
        specialties.value = [];
        services.value = [];
        professionals.value = [];
        dates.value = [];
        times.value = [];
        error.value = null;
    }

    const isAnyProfessional = computed(
        () => selectedProfessionalId.value === ANY_PROFESSIONAL,
    );

    return {
        ANY_PROFESSIONAL,
        units,
        specialties,
        services,
        professionals,
        dates,
        times,
        selectedUnitId,
        selectedSpecialtyId,
        selectedServiceId,
        selectedProfessionalId,
        selectedDate,
        currentMonth,
        isLoading,
        error,
        isAnyProfessional,
        loadUnits,
        selectUnit,
        selectSpecialty,
        selectService,
        selectProfessional,
        selectDate,
        changeMonth,
        reset,
    };
}
