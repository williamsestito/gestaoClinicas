<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Site\SiteCollectionItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ReorderSiteCollectionRequest;
use App\Http\Requests\Organization\SiteFaqRequest;
use App\Models\SiteFaq;
use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteFaqController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $organization = $tenant->organization();
        $this->authorize('view', [SiteSetting::class, $organization]);

        return Inertia::render('settings/site/faq/Index', [
            'faqs' => SiteFaq::query()
                ->orderBy('order')
                ->orderBy('id')
                ->get()
                ->map(fn (SiteFaq $faq) => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category,
                    'order' => $faq->order,
                    'is_active' => $faq->is_active,
                ])
                ->all(),
        ]);
    }

    public function store(SiteFaqRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->upsert(new SiteFaq, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pergunta criada com sucesso.']);

        return back();
    }

    public function update(SiteFaqRequest $request, SiteFaq $siteFaq, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->upsert($siteFaq, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pergunta atualizada com sucesso.']);

        return back();
    }

    public function destroy(TenantContext $tenant, SiteFaq $siteFaq, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->delete($siteFaq);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pergunta excluída.']);

        return back();
    }

    public function toggle(TenantContext $tenant, SiteFaq $siteFaq, SiteCollectionItemAction $action): RedirectResponse
    {
        $this->authorize('update', [SiteSetting::class, $tenant->organization()]);

        $action->toggleActive($siteFaq);

        return back();
    }

    public function reorder(ReorderSiteCollectionRequest $request, SiteCollectionItemAction $action): RedirectResponse
    {
        $action->reorder(SiteFaq::class, $request->validated('ids'));

        return back();
    }
}
