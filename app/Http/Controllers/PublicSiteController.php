<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Seo\SeoMetaBuilder;
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
        $site = SiteSetting::query()->first();

        return Inertia::render('Welcome', [
            'organizationConfigured' => $organization !== null,
            'site' => $site ? [
                'title' => $site->title,
                'description' => $site->description,
                'hero_image_url' => $site->hero_image_path
                    ? Storage::disk('public')->url($site->hero_image_path)
                    : null,
                'primary_color' => $site->primary_color,
                'secondary_color' => $site->secondary_color,
            ] : null,
            'seo' => $this->seoMetaBuilder->forHome($organization, $site),
        ]);
    }
}
