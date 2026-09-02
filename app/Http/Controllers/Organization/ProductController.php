<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProductAction;
use App\Actions\Organization\CreateProductAction;
use App\Actions\Organization\DeactivateProductAction;
use App\Actions\Organization\DeleteProductAction;
use App\Actions\Organization\RestoreProductAction;
use App\Actions\Organization\UpdateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateProductRequest;
use App\Http\Requests\Organization\UpdateProductRequest;
use App\Models\Product;
use App\Queries\ProductListQuery;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(TenantContext $tenant, ProductListQuery $query): Response
    {
        $this->authorize('viewAny', [Product::class, $tenant->organization()]);

        return Inertia::render('settings/products/Index', [
            'products' => $query->forOrganization($tenant->organization()),
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [Product::class, $tenant->organization()]);

        return Inertia::render('settings/products/Create');
    }

    public function store(CreateProductRequest $request, CreateProductAction $action, TenantContext $tenant): RedirectResponse
    {
        $action->handle($tenant->organization(), $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto cadastrado com sucesso.']);

        return to_route('settings.products.index');
    }

    public function edit(Product $product): Response
    {
        $this->authorize('view', $product);

        return Inertia::render('settings/products/Edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'barcode' => $product->barcode,
                'unit_of_measure' => $product->unit_of_measure,
                'cost' => $product->cost_cents !== null ? $product->cost_cents / 100 : null,
                'margin_percentage' => $product->margin_percentage,
                'price' => $product->price_cents !== null ? $product->price_cents / 100 : null,
                'max_discount_percentage' => $product->max_discount_percentage,
                'internal_notes' => $product->internal_notes,
                'status' => $product->status->value,
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProductAction $action): RedirectResponse
    {
        $action->handle($product, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto alterado com sucesso.']);

        return to_route('settings.products.index');
    }

    public function activate(Product $product, ActivateProductAction $action): RedirectResponse
    {
        $this->authorize('activate', $product);

        $action->handle($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto ativado com sucesso.']);

        return back();
    }

    public function deactivate(Product $product, DeactivateProductAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $product);

        $action->handle($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto inativado com sucesso.']);

        return back();
    }

    public function destroy(Product $product, DeleteProductAction $action): RedirectResponse
    {
        $this->authorize('delete', $product);

        $action->handle($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto excluído com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(string $product, TenantContext $tenant, RestoreProductAction $action): RedirectResponse
    {
        $entity = Product::withTrashed()->findOrFail($product);

        if (! $tenant->organization() || $entity->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $entity);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto restaurado com sucesso.']);

        return back();
    }
}
