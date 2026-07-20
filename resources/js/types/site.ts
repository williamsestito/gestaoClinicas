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

export interface SiteServiceOption {
    id: number;
    name: string;
}

export const LANDING_SECTION_TYPES = [
    'hero',
    'benefits',
    'about',
    'services',
    'professionals',
    'gallery',
    'testimonials',
    'cta',
    'scheduling',
    'contact',
    'faq',
] as const;

export type LandingSectionType = (typeof LANDING_SECTION_TYPES)[number];

export interface LandingSection {
    type: LandingSectionType;
    active: boolean;
}

export const LANDING_SECTION_LABELS: Record<LandingSectionType, string> = {
    hero: 'Banner principal',
    benefits: 'Diferenciais',
    about: 'Sobre a clínica',
    services: 'Serviços',
    professionals: 'Equipe',
    gallery: 'Galeria',
    testimonials: 'Depoimentos',
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
    service_name: string | null;
    preferred_period: string | null;
    notes: string | null;
    status: AppointmentRequestStatus;
    status_label: string;
    created_at: string | null;
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
    hero_image_url: string | null;
    logo_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
    cta_text: string | null;
    cta_url: string | null;
    cta_secondary_text: string | null;
    cta_secondary_url: string | null;
    about_text: string | null;
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
    id: number;
    name: string;
    role_title: string | null;
    specialty: string | null;
    professional_register: string | null;
    bio: string | null;
    photo_url: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    linkedin_url: string | null;
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
