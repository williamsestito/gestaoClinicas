export interface SiteCollectionItemBase {
    id: number;
    order: number;
    is_active: boolean;
}

export interface SiteBenefit extends SiteCollectionItemBase {
    icon: string | null;
    title: string;
    description: string | null;
}

export interface LinkedOperationalRecord {
    id: string;
    name: string;
    is_operational: boolean;
}

export interface SiteService extends SiteCollectionItemBase {
    name: string;
    short_description: string | null;
    description: string | null;
    image_url: string | null;
    icon: string | null;
    category: string | null;
    duration_minutes: number | null;
    starting_price_cents: number | null;
    cta_text: string | null;
    is_featured: boolean;
    service_id: string | null;
    linked_service: LinkedOperationalRecord | null;
}

export interface SiteProfessional extends SiteCollectionItemBase {
    name: string;
    role_title: string | null;
    specialty: string | null;
    professional_register: string | null;
    bio: string | null;
    photo_url: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    linkedin_url: string | null;
    professional_id: string | null;
    linked_professional: LinkedOperationalRecord | null;
}

export interface SiteGalleryItem extends SiteCollectionItemBase {
    image_url: string;
    caption: string | null;
    alt_text: string | null;
    category: string | null;
    is_cover: boolean;
}

export interface SiteTestimonial extends SiteCollectionItemBase {
    author_name: string;
    author_photo_url: string | null;
    rating: number | null;
    content: string;
    related_service_id: number | null;
    related_service_name: string | null;
    is_featured: boolean;
}

export interface SiteFaq extends SiteCollectionItemBase {
    question: string;
    answer: string;
    category: string | null;
}

export interface SitePartner extends SiteCollectionItemBase {
    name: string;
    logo_url: string | null;
    url: string | null;
}

export interface SiteServiceOption {
    id: number;
    name: string;
}

export const LANDING_SECTION_TYPES = [
    'hero',
    'statistics',
    'about',
    'services',
    'professionals',
    'benefits',
    'gallery',
    'testimonials',
    'partners',
    'scheduling',
    'cta',
    'faq',
    'contact',
] as const;

export type LandingSectionType = (typeof LANDING_SECTION_TYPES)[number];

export interface LandingSection {
    type: LandingSectionType;
    active: boolean;
}

export const LANDING_SECTION_LABELS: Record<LandingSectionType, string> = {
    hero: 'Banner principal',
    statistics: 'Indicadores',
    benefits: 'Diferenciais',
    about: 'Sobre a clínica',
    services: 'Serviços',
    professionals: 'Equipe',
    gallery: 'Galeria',
    testimonials: 'Depoimentos',
    partners: 'Convênios e parceiros',
    cta: 'Chamada para ação',
    scheduling: 'Agendamento',
    contact: 'Contato e localização',
    faq: 'Perguntas frequentes',
};

export type AppointmentRequestStatus =
    'pending' | 'contacted' | 'scheduled' | 'cancelled';

export interface AppointmentRequestSummary {
    id: string;
    name: string;
    phone: string;
    email: string | null;
    document?: string | null;
    service_name: string | null;
    preferred_period: string | null;
    preferred_date: string | null;
    notes: string | null;
    internal_notes: string | null;
    utm_data?: Record<string, string> | null;
    status: AppointmentRequestStatus;
    status_label: string;
    created_at: string | null;
    updated_at?: string | null;
    appointment_status?: string | null;
    appointment_status_label?: string | null;
    professional_id: string | null;
    professional_name: string | null;
    // Estruturados (unidade/serviço reais + horário exato) — só presentes
    // quando o lead veio de um horário específico escolhido na busca de
    // disponibilidade da landing. Quando os três estão presentes, "Agendar"
    // vira um popup de confirmação em vez de abrir a tela de conversão
    // manual (ver components/appointment-requests/InstantScheduleModal.vue).
    unit_id: string | null;
    unit_name: string | null;
    preferred_service_id: string | null;
    preferred_service_name: string | null;
    preferred_starts_at: string | null;
    patient_id: string | null;
    patient_name: string | null;
}

export interface SiteContactAddress {
    street: string;
    number: string;
    city: string;
    state: string;
    postal_code?: string;
}

export interface SiteContact {
    phone: string | null;
    whatsapp: string | null;
    email: string | null;
    address: SiteContactAddress | null;
}

export interface PublicSiteContent {
    title: string;
    description: string | null;
    schema_type_label: string | null;
    hero_image_url: string | null;
    hero_image_mobile_url: string | null;
    logo_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
    cta_text: string | null;
    cta_url: string | null;
    cta_secondary_text: string | null;
    cta_secondary_url: string | null;
    about_text: string | null;
    mission_text: string | null;
    vision_text: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    linkedin_url: string | null;
    footer_text: string | null;
}

export interface PublicOpeningHour {
    day_of_week: number;
    opens_at: string;
    closes_at: string;
}

export interface PublicContact {
    name: string;
    phone: string | null;
    whatsapp: string | null;
    email: string | null;
    address: SiteContactAddress | null;
    opening_hours: PublicOpeningHour[];
    map_url: string | null;
}

export interface PublicService {
    id: number;
    name: string;
    short_description: string | null;
    description: string | null;
    image_url: string | null;
    icon: string | null;
    category: string | null;
    duration_minutes: number | null;
    starting_price_cents: number | null;
    cta_text: string | null;
    is_featured: boolean;
}

export interface PublicProfessional {
    id: string;
    professional_id: string | null;
    name: string;
    role_title: string | null;
    specialty: string | null;
    professional_register: string | null;
    bio: string | null;
    photo_url: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    linkedin_url: string | null;
    order: number;
}

export interface PublicGalleryItem {
    id: number;
    image_url: string;
    caption: string | null;
    alt_text: string | null;
    category: string | null;
    is_cover: boolean;
}

export interface PublicTestimonial {
    id: number;
    author_name: string;
    author_photo_url: string | null;
    rating: number | null;
    content: string;
    related_service_name: string | null;
    is_featured: boolean;
}

export interface PublicBenefit {
    id: number;
    icon: string | null;
    title: string;
    description: string | null;
}

export interface PublicFaq {
    id: number;
    question: string;
    answer: string;
    category: string | null;
}

export interface PublicPartner {
    id: number;
    name: string;
    logo_url: string | null;
    url: string | null;
}

export interface PublicStatistic {
    value: string;
    label: string;
}
