<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\Service;
use App\Models\SiteBenefit;
use App\Models\SiteFaq;
use App\Models\SiteGalleryItem;
use App\Models\SitePartner;
use App\Models\SiteProfessional;
use App\Models\SiteService;
use App\Models\SiteSetting;
use App\Models\SiteTestimonial;
use App\Queries\PublicProfessionalQuery;
use App\Support\Seo\SeoMetaBuilder;
use App\Support\Site\LandingSections;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    public function __construct(
        private readonly SeoMetaBuilder $seoMetaBuilder,
        private readonly PublicProfessionalQuery $publicProfessionalQuery,
    ) {}

    public function home(): Response
    {
        // Instalação single-tenant: carrega "a" organização desta
        // instância — nunca uma lista para escolher, nunca a primeira
        // dentre várias clínicas-cliente. Se ainda não houver nenhuma
        // (onboarding não concluído), a página exibe um estado de
        // configuração pendente, nunca um erro técnico.
        $organization = Organization::query()->first();
        $siteSetting = SiteSetting::query()->first();

        // Enquanto o site não for publicado, a página pública se comporta
        // como se o conteúdo ainda não existisse — o admin pode preparar o
        // conteúdo com calma antes de torná-lo público. Isso vale para
        // todo o conteúdo administrado (hero, seções, coleções), não só
        // para os campos do singleton.
        $site = ($siteSetting && $siteSetting->is_published) ? $siteSetting : null;
        $headquarters = $site ? $organization?->headquarters()->with(['address', 'openingHours'])->first() : null;

        return Inertia::render('Welcome', [
            'organizationConfigured' => $organization !== null,
            'site' => $site ? [
                'title' => $site->title,
                'description' => $site->description,
                'schema_type_label' => $site->schema_type->label(),
                'hero_image_url' => $site->hero_image_path
                    ? Storage::disk('public')->url($site->hero_image_path)
                    : null,
                'hero_image_mobile_url' => $site->hero_image_mobile_path
                    ? Storage::disk('public')->url($site->hero_image_mobile_path)
                    : null,
                'logo_url' => $site->logo_path
                    ? Storage::disk('public')->url($site->logo_path)
                    : null,
                'primary_color' => $site->primary_color,
                'secondary_color' => $site->secondary_color,
                'cta_text' => $site->cta_text,
                'cta_url' => $site->cta_url,
                'cta_secondary_text' => $site->cta_secondary_text,
                'cta_secondary_url' => $site->cta_secondary_url,
                'about_text' => $site->about_text,
                'mission_text' => $site->mission_text,
                'vision_text' => $site->vision_text,
                'facebook_url' => $site->facebook_url,
                'instagram_url' => $site->instagram_url,
                'linkedin_url' => $site->linkedin_url,
                'footer_text' => $site->footer_text,
            ] : null,
            'sections' => $site ? LandingSections::normalize($site->sections_config) : [],
            'benefits' => $site ? $this->activeBenefits() : [],
            'services' => $site ? $this->activeServices($organization) : [],
            'professionals' => $site ? $this->activeProfessionals($organization) : [],
            'gallery' => $site ? $this->activeGallery() : [],
            'testimonials' => $site ? $this->activeTestimonials() : [],
            'faqs' => $site ? $this->activeFaqs() : [],
            'partners' => $site ? $this->activePartners() : [],
            'statistics' => $site ? $this->statistics($organization) : [],
            'contact' => $site && $headquarters ? [
                'name' => $organization->name,
                'phone' => $headquarters->phone,
                'whatsapp' => $headquarters->whatsapp,
                'email' => $headquarters->email,
                'address' => $headquarters->address ? [
                    'street' => $headquarters->address->street,
                    'number' => $headquarters->address->number,
                    'city' => $headquarters->address->city,
                    'state' => $headquarters->address->state,
                    'postal_code' => $headquarters->address->postal_code,
                ] : null,
                'opening_hours' => $headquarters->openingHours->map(fn ($hour) => [
                    'day_of_week' => $hour->day_of_week,
                    'opens_at' => $hour->opens_at,
                    'closes_at' => $hour->closes_at,
                ])->all(),
                'map_url' => $siteSetting->google_maps_url,
                'latitude' => $siteSetting->latitude,
                'longitude' => $siteSetting->longitude,
            ] : null,
            'seo' => $this->seoMetaBuilder->forHome($organization, $site),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeBenefits(): array
    {
        return SiteBenefit::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (SiteBenefit $benefit) => [
                'id' => $benefit->id,
                'icon' => $benefit->icon,
                'title' => $benefit->title,
                'description' => $benefit->description,
            ])
            ->all();
    }

    /**
     * Um item promocional sem vínculo aparece sempre (comportamento
     * inalterado). Um item vinculado só aparece se o registro operacional
     * ainda existir, não estiver excluído, estiver ativo e pertencer à
     * organização carregada nesta instância — nunca apaga o conteúdo
     * promocional, apenas oculta a publicação enquanto a situação
     * operacional não permitir.
     */
    private function isPubliclyEligible(Professional|Service|null $linked, ?Organization $organization): bool
    {
        if ($linked === null) {
            return true;
        }

        return $organization !== null
            && $linked->organization_id === $organization->id
            && $linked->deleted_at === null
            && $linked->status === RecordStatus::Active;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeServices(?Organization $organization): array
    {
        return SiteService::query()
            ->where('is_active', true)
            ->with(['service' => fn ($query) => $query->withTrashed()->select(['id', 'organization_id', 'status', 'deleted_at'])])
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->filter(fn (SiteService $service) => $this->isPubliclyEligible($service->service, $organization))
            ->values()
            ->map(fn (SiteService $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'short_description' => $service->short_description,
                'description' => $service->description,
                'image_url' => $service->image_path ? Storage::disk('public')->url($service->image_path) : null,
                'icon' => $service->icon,
                'category' => $service->category,
                'duration_minutes' => $service->duration_minutes,
                'starting_price_cents' => $service->starting_price_cents,
                'cta_text' => $service->cta_text,
                'is_featured' => $service->is_featured,
            ])
            ->all();
    }

    /**
     * Fonte única e normalizada (SiteProfessional + Professional público,
     * deduplicados) — ver App\Queries\PublicProfessionalQuery. A mesma
     * fonte alimenta o filtro de profissional da busca de disponibilidade
     * pública, nunca uma consulta divergente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activeProfessionals(?Organization $organization): array
    {
        if ($organization === null) {
            return [];
        }

        return $this->publicProfessionalQuery->forOrganization($organization)->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeGallery(): array
    {
        return SiteGalleryItem::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (SiteGalleryItem $item) => [
                'id' => $item->id,
                'image_url' => Storage::disk('public')->url($item->image_path),
                'caption' => $item->caption,
                'alt_text' => $item->alt_text,
                'category' => $item->category,
                'is_cover' => $item->is_cover,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeTestimonials(): array
    {
        return SiteTestimonial::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->with('relatedService:id,name')
            ->get()
            ->map(fn (SiteTestimonial $testimonial) => [
                'id' => $testimonial->id,
                'author_name' => $testimonial->author_name,
                'author_photo_url' => $testimonial->author_photo_path ? Storage::disk('public')->url($testimonial->author_photo_path) : null,
                'rating' => $testimonial->rating,
                'content' => $testimonial->content,
                'related_service_name' => $testimonial->relatedService?->name,
                'is_featured' => $testimonial->is_featured,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeFaqs(): array
    {
        return SiteFaq::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (SiteFaq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'category' => $faq->category,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activePartners(): array
    {
        return SitePartner::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (SitePartner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'logo_url' => $partner->logo_path ? Storage::disk('public')->url($partner->logo_path) : null,
                'url' => $partner->url,
            ])
            ->all();
    }

    /**
     * Indicadores da faixa "estatísticas" logo abaixo do hero — sempre
     * calculados a partir de dados reais (nunca um número configurado à
     * mão). Um indicador só aparece se o valor for maior que zero, para
     * nunca publicar "0 profissionais" como se fosse um destaque.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function statistics(?Organization $organization): array
    {
        $professionalsCount = $organization ? $this->publicProfessionalQuery->forOrganization($organization)->count() : 0;

        $specialtiesCount = SiteProfessional::query()
            ->where('is_active', true)
            ->whereNotNull('specialty')
            ->distinct()
            ->count('specialty');

        $servicesCount = SiteService::query()->where('is_active', true)->count();

        $unitsCount = $organization
            ? $organization->units()->where('status', RecordStatus::Active)->count()
            : 0;

        $candidates = [
            ['value' => $professionalsCount, 'label' => $professionalsCount === 1 ? 'Profissional' : 'Profissionais'],
            ['value' => $specialtiesCount, 'label' => $specialtiesCount === 1 ? 'Especialidade' : 'Especialidades'],
            ['value' => $servicesCount, 'label' => $servicesCount === 1 ? 'Serviço oferecido' : 'Serviços oferecidos'],
            ['value' => $unitsCount, 'label' => $unitsCount === 1 ? 'Unidade' : 'Unidades'],
        ];

        return collect($candidates)
            ->filter(fn (array $stat) => $stat['value'] > 0)
            ->map(fn (array $stat) => ['value' => (string) $stat['value'], 'label' => $stat['label']])
            ->values()
            ->all();
    }
}
