<?php

namespace App\Http\Middleware;

use App\Enums\PatientUserLinkRole;
use App\Models\PatientUserLink;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContextPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user('web'),
            ],
            'tenant' => fn () => app(TenantContextPresenter::class)->toArray($request->user('web')),
            // Compartilhado globalmente (não só na home do portal) porque
            // PatientPortalLayout.vue usa isso para o item "Meus dados" do
            // menu ir direto para o próprio cadastro, em qualquer página.
            'patientPortal' => fn () => $request->user('patient') ? [
                'ownPatientId' => PatientUserLink::query()
                    ->where('patient_user_id', $request->user('patient')->id)
                    ->where('role', PatientUserLinkRole::Self)
                    ->value('patient_id'),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Favicon do site institucional — usado por app.blade.php para
            // renderizar as tags <link rel="icon"> reais em TODA página
            // (landing pública e painel), nunca só na home. Vazio ([])
            // enquanto nenhum favicon foi enviado ainda: o blade não
            // renderiza tag alguma nesse caso (nunca tag vazia).
            'favicon' => fn () => SiteSetting::query()->first()?->faviconUrls() ?? [],
        ];
    }
}
