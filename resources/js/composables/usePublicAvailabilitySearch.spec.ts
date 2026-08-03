import { afterEach, describe, expect, it, vi } from 'vitest';
import { usePublicAvailabilitySearch } from './usePublicAvailabilitySearch';

function jsonResponse(data: unknown[]) {
    return { ok: true, json: async () => ({ data }) };
}

describe('usePublicAvailabilitySearch', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('loads units and clears the error on success', async () => {
        const unit = {
            id: 'unit-1',
            name: 'Unidade Centro',
            neighborhood: null,
            city: 'São Paulo',
            state: 'SP',
        };
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([unit])));

        const { units, loading, error, loadUnits } =
            usePublicAvailabilitySearch();
        expect(loading.value).toBeNull();

        await loadUnits();

        expect(units.value).toEqual([unit]);
        expect(error.value).toBeNull();
        expect(loading.value).toBeNull();
    });

    it('sets a pt-BR error message and keeps the list empty when the units request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        const { units, error, loadUnits } = usePublicAvailabilitySearch();
        await loadUnits();

        expect(units.value).toEqual([]);
        expect(error.value).toBe(
            'Não foi possível carregar as unidades. Tente novamente.',
        );
    });

    it('sets a pt-BR error message when fetch rejects (network failure)', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        const { error, loadUnits } = usePublicAvailabilitySearch();
        await loadUnits();

        expect(error.value).toBe(
            'Não foi possível carregar as unidades. Tente novamente.',
        );
    });

    it('selectUnit resets every downstream selection and list before refetching', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse([]));
        vi.stubGlobal('fetch', fetchMock);

        const search = usePublicAvailabilitySearch();
        search.selectedSpecialtyId.value = 'spec-1';
        search.selectedServiceId.value = 'svc-1';
        search.selectedProfessionalId.value = 'prof-1';
        search.selectedDate.value = '2026-08-10';
        search.services.value = [
            {
                id: 'svc-1',
                name: 'Consulta',
                description: null,
                default_duration_minutes: 30,
            },
        ];
        search.professionals.value = [
            { id: 'prof-1', name: 'Dra. Ana', photo_url: null },
        ];
        search.dates.value = [{ date: '2026-08-10', is_available: true }];
        search.times.value = [
            {
                time: '09:00',
                professional_id: 'prof-1',
                professional_name: 'Dra. Ana',
                unit_name: 'Centro',
                service_name: 'Consulta',
                duration_minutes: 30,
            },
        ];

        search.selectUnit('unit-2');

        expect(search.selectedUnitId.value).toBe('unit-2');
        expect(search.selectedSpecialtyId.value).toBeNull();
        expect(search.selectedServiceId.value).toBeNull();
        expect(search.selectedProfessionalId.value).toBeNull();
        expect(search.selectedDate.value).toBeNull();
        expect(search.services.value).toEqual([]);
        expect(search.professionals.value).toEqual([]);
        expect(search.dates.value).toEqual([]);
        expect(search.times.value).toEqual([]);

        await Promise.resolve();
        await Promise.resolve();

        const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
        expect(
            calledUrls.some(
                (url) =>
                    url.includes('/disponibilidade/especialidades') &&
                    url.includes('unit_id=unit-2'),
            ),
        ).toBe(true);
        expect(
            calledUrls.some(
                (url) =>
                    url.includes('/disponibilidade/servicos') &&
                    url.includes('unit_id=unit-2'),
            ),
        ).toBe(true);
    });

    it('selectSpecialty resets service/professional/date selections but keeps the unit', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));

        const search = usePublicAvailabilitySearch();
        search.selectedUnitId.value = 'unit-1';
        search.selectedServiceId.value = 'svc-1';
        search.selectedProfessionalId.value = 'prof-1';
        search.selectedDate.value = '2026-08-10';

        search.selectSpecialty('spec-2');

        expect(search.selectedUnitId.value).toBe('unit-1');
        expect(search.selectedSpecialtyId.value).toBe('spec-2');
        expect(search.selectedServiceId.value).toBeNull();
        expect(search.selectedProfessionalId.value).toBeNull();
        expect(search.selectedDate.value).toBeNull();
    });

    it('selectService triggers both professionals and dates requests with the right query params', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse([]));
        vi.stubGlobal('fetch', fetchMock);

        const search = usePublicAvailabilitySearch();
        search.selectedUnitId.value = 'unit-1';
        search.selectedSpecialtyId.value = 'spec-1';

        search.selectService('svc-1');

        expect(search.selectedServiceId.value).toBe('svc-1');
        expect(search.selectedProfessionalId.value).toBeNull();
        expect(search.selectedDate.value).toBeNull();

        await Promise.resolve();
        await Promise.resolve();

        const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
        const professionalsCall = calledUrls.find((url) =>
            url.includes('/disponibilidade/profissionais'),
        );
        const datesCall = calledUrls.find((url) =>
            url.includes('/disponibilidade/datas'),
        );

        expect(professionalsCall).toContain('unit_id=unit-1');
        expect(professionalsCall).toContain('service_id=svc-1');
        expect(professionalsCall).toContain('specialty_id=spec-1');
        expect(datesCall).toContain('service_id=svc-1');
    });

    it('never sends professional_id when the "any professional" pseudo-option is selected', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse([]));
        vi.stubGlobal('fetch', fetchMock);

        const search = usePublicAvailabilitySearch();
        search.selectedUnitId.value = 'unit-1';
        search.selectedServiceId.value = 'svc-1';

        search.selectProfessional(search.ANY_PROFESSIONAL);
        expect(search.isAnyProfessional.value).toBe(true);

        await Promise.resolve();
        await Promise.resolve();

        const datesCall = fetchMock.mock.calls
            .map((call) => String(call[0]))
            .find((url) => url.includes('/disponibilidade/datas'));
        expect(datesCall).not.toContain('professional_id');
    });

    it('sends professional_id when a specific professional is selected', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse([]));
        vi.stubGlobal('fetch', fetchMock);

        const search = usePublicAvailabilitySearch();
        search.selectedUnitId.value = 'unit-1';
        search.selectedServiceId.value = 'svc-1';

        search.selectProfessional('prof-7');
        expect(search.isAnyProfessional.value).toBe(false);

        await Promise.resolve();
        await Promise.resolve();

        const datesCall = fetchMock.mock.calls
            .map((call) => String(call[0]))
            .find((url) => url.includes('/disponibilidade/datas'));
        expect(datesCall).toContain('professional_id=prof-7');
    });

    it('selectDate loads times scoped to the selected unit/service/date', async () => {
        const slot = {
            time: '09:00',
            professional_id: 'prof-1',
            professional_name: 'Dra. Ana',
            unit_name: 'Centro',
            service_name: 'Consulta',
            duration_minutes: 30,
        };
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse([slot]));
        vi.stubGlobal('fetch', fetchMock);

        const search = usePublicAvailabilitySearch();
        search.selectedUnitId.value = 'unit-1';
        search.selectedServiceId.value = 'svc-1';

        search.selectDate('2026-08-10');
        expect(search.selectedDate.value).toBe('2026-08-10');

        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        expect(search.times.value).toEqual([slot]);
        const timesCall = fetchMock.mock.calls
            .map((call) => String(call[0]))
            .find((url) => url.includes('/disponibilidade/horarios'));
        expect(timesCall).toContain('date=2026-08-10');
    });

    it('changeMonth updates currentMonth and reloads the calendar', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse([]));
        vi.stubGlobal('fetch', fetchMock);

        const search = usePublicAvailabilitySearch();
        search.selectedUnitId.value = 'unit-1';
        search.selectedServiceId.value = 'svc-1';
        fetchMock.mockClear();

        search.changeMonth('2026-09');

        expect(search.currentMonth.value).toBe('2026-09');
        await Promise.resolve();
        await Promise.resolve();

        const datesCall = fetchMock.mock.calls
            .map((call) => String(call[0]))
            .find((url) => url.includes('/disponibilidade/datas'));
        expect(datesCall).toContain('month=2026-09');
    });

    it('does not call the times endpoint when a date is selected without unit/service prerequisites', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse([]));
        vi.stubGlobal('fetch', fetchMock);

        const search = usePublicAvailabilitySearch();
        // Sem unidade/serviço selecionados, selectDate() não deve disparar fetch.
        search.selectDate('2026-08-10');
        await Promise.resolve();
        await Promise.resolve();

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('is not a singleton — each call returns independent state', () => {
        const first = usePublicAvailabilitySearch();
        const second = usePublicAvailabilitySearch();

        first.selectedUnitId.value = 'unit-1';

        expect(second.selectedUnitId.value).toBeNull();
    });
});
