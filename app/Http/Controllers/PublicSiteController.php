<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SiteBenefit;
use App\Models\SiteFaq;
use App\Models\SiteGalleryItem;
use App\Models\SiteProfessional;
use App\Models\SiteService;
use App\Models\SiteSetting;
use App\Models\SiteTestimonial;
use App\Support\Seo\SeoMetaBuilder;
use App\Support\Site\LandingSections;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    public function __construct(private readonly SeoMetaBuilder $seoMetaBuilder) {}

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
                'hero_image_url' => $site->hero_image_path
                    ? Storage::disk('public')->url($site->hero_image_path)
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
                'facebook_url' => $site->facebook_url,
                'instagram_url' => $site->instagram_url,
                'linkedin_url' => $site->linkedin_url,
                'footer_text' => $site->footer_text,
            ] : null,
            'sections' => $site ? LandingSections::normalize($site->sections_config) : [],
            'benefits' => $site ? $this->activeBenefits() : [],
            'services' => $site ? $this->activeServices() : [],
            'professionals' => $site ? $this->activeProfessionals() : [],
            'gallery' => $site ? $this->activeGallery() : [],
            'testimonials' => $site ? $this->activeTestimonials() : [],
            'faqs' => $site ? $this->activeFaqs() : [],
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
     * @return array<int, array<string, mixed>>
     */
    private function activeServices(): array
    {
        return SiteService::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
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
     * @return array<int, array<string, mixed>>
     */
    private function activeProfessionals(): array
    {
        return SiteProfessional::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(fn (SiteProfessional $professional) => [
                'id' => $professional->id,
                'name' => $professional->name,
                'role_title' => $professional->role_title,
                'specialty' => $professional->specialty,
                'professional_register' => $professional->professional_register,
                'bio' => $professional->bio,
                'photo_url' => $professional->photo_path ? Storage::disk('public')->url($professional->photo_path) : null,
                'facebook_url' => $professional->facebook_url,
                'instagram_url' => $professional->instagram_url,
                'linkedin_url' => $professional->linkedin_url,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeGallery(): array
    {
        return SiteGalleryItem::query()
            ->where('is_active', true)
            ->orderBy('order')
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
            ->get()
            ->map(fn (SiteFaq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'category' => $faq->category,
            ])
            ->all();
    }
}
