export interface AddressForm {
    postal_code: string;
    street: string;
    number: string;
    complement: string;
    neighborhood: string;
    city: string;
    state: string;
}

export interface OpeningHourForm {
    day_of_week: number;
    opens_at: string;
    closes_at: string;
}

export interface Organization {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'inactive' | 'suspended';
    default_timezone: string;
    default_currency: string;
    locale: string;
    primary_color: string | null;
    secondary_color: string | null;
    allow_appointment_overlap: boolean;
}

export interface Unit {
    id: string;
    organization_id: string;
    legal_entity_id: string;
    name: string;
    code: string;
    slug: string;
    status: 'active' | 'inactive';
    is_headquarters: boolean;
    timezone: string;
    email: string | null;
    phone: string | null;
    whatsapp: string | null;
    deleted_at: string | null;
}

export interface LegalEntity {
    id: string;
    organization_id: string;
    type: 'individual' | 'company';
    document: string;
    legal_name: string;
    trade_name: string | null;
    state_registration?: string | null;
    municipal_registration?: string | null;
    email?: string | null;
    phone?: string | null;
    is_primary: boolean;
    status: 'active' | 'inactive';
    deleted_at: string | null;
}

export interface TenantOrganizationSummary {
    id: string;
    name: string;
    slug: string;
}

export interface TenantUnitSummary {
    id: string;
    name: string;
    code: string;
    is_headquarters: boolean;
}

export interface TenantContext {
    organization: (TenantOrganizationSummary & { status: string }) | null;
    unit: (TenantUnitSummary & { status: string }) | null;
    membership: { id: string; status: string; is_owner: boolean } | null;
    availableOrganizations: TenantOrganizationSummary[];
    availableUnits: TenantUnitSummary[];
    isOwner: boolean;
    isUnitManager: boolean;
    isPlatformAdmin: boolean;
    /** Só para refletir a navegação/UI — nunca a fonte de autorização real. */
    permissions: string[];
}

export const WEEKDAYS = [
    { value: 0, label: 'Domingo' },
    { value: 1, label: 'Segunda-feira' },
    { value: 2, label: 'Terça-feira' },
    { value: 3, label: 'Quarta-feira' },
    { value: 4, label: 'Quinta-feira' },
    { value: 5, label: 'Sexta-feira' },
    { value: 6, label: 'Sábado' },
] as const;
