<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ApproveSaleItemDiscountAction;
use App\Actions\Organization\CancelSaleAction;
use App\Actions\Organization\ConfirmSaleAction;
use App\Actions\Organization\CreateSaleAction;
use App\Actions\Organization\UpdateSaleDraftAction;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ApproveSaleItemDiscountRequest;
use App\Http\Requests\Organization\CancelSaleRequest;
use App\Http\Requests\Organization\ConfirmSaleRequest;
use App\Http\Requests\Organization\CreateSaleRequest;
use App\Http\Requests\Organization\UpdateSaleDraftRequest;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Queries\SaleListQuery;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request, TenantContext $tenant, SaleListQuery $query): Response
    {
        $this->authorize('viewAny', [Sale::class, $tenant->organization()]);

        $status = $request->string('status')->value();

        $sales = $query->forOrganization(
            $tenant->organization(),
            patientId: $request->string('patient_id')->value() ?: null,
            unitId: $request->string('unit_id')->value() ?: null,
            status: $status !== '' ? SaleStatus::from($status) : null,
        )->through(fn (Sale $sale) => [
            'id' => $sale->id,
            'patient_name' => $sale->patient->preferred_name ?: $sale->patient->name,
            'unit_name' => $sale->unit->name,
            'status' => $sale->status->value,
            'status_label' => $sale->status->label(),
            'total_cents' => $sale->total_cents,
            'created_at' => $sale->created_at->toIso8601String(),
        ]);

        return Inertia::render('settings/sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['patient_id', 'unit_id', 'status']),
        ]);
    }

    public function create(Request $request, TenantContext $tenant): Response
    {
        $this->authorize('create', [Sale::class, $tenant->organization()]);

        $organization = $tenant->organization();
        $patientId = $request->string('patient_id')->value();
        $patient = $patientId !== '' ? Patient::query()->where('organization_id', $organization->id)->find($patientId) : null;

        $activeUnit = $tenant->unit();
        $primaryLegalEntity = $organization->legalEntities()->where('is_primary', true)->first()
            ?? $organization->legalEntities()->orderBy('legal_name')->first();

        return Inertia::render('settings/sales/Create', [
            'patient' => $patient ? ['id' => $patient->id, 'name' => $patient->preferred_name ?: $patient->name] : null,
            // Unidade e entidade legal não são escolhidas na tela — a venda
            // sempre usa a unidade ativa (trocada pelo seletor do topo) e a
            // entidade legal principal da organização, mesmo padrão de
            // "contexto ativo" já usado pelo resto do app.
            'unit' => $activeUnit ? ['id' => $activeUnit->id, 'name' => $activeUnit->name] : null,
            'legalEntity' => $primaryLegalEntity ? ['id' => $primaryLegalEntity->id, 'name' => $primaryLegalEntity->legal_name] : null,
            'services' => Service::query()->where('organization_id', $organization->id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'default_price_cents', 'max_discount_percentage'])->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'default_price_cents' => $service->default_price_cents,
                'max_discount_percentage' => $service->max_discount_percentage,
            ]),
            'products' => Product::query()->where('organization_id', $organization->id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'price_cents', 'max_discount_percentage'])->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price_cents' => $product->price_cents,
                'max_discount_percentage' => $product->max_discount_percentage,
            ]),
        ]);
    }

    public function store(CreateSaleRequest $request, CreateSaleAction $action, TenantContext $tenant): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $sale = $action->handle($tenant->organization(), $user, $request->saleAttributes(), $request->itemsForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Venda criada com sucesso.']);

        return to_route('settings.sales.show', $sale);
    }

    public function show(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        $sale->load(['patient', 'unit', 'legalEntity', 'professional', 'items.service', 'items.product', 'items.approver']);

        return Inertia::render('settings/sales/Show', [
            'sale' => $this->presentSale($sale),
            'canEdit' => $sale->status === SaleStatus::Draft || $sale->status === SaleStatus::PendingApproval,
            'canConfirm' => request()->user()?->can('confirm', $sale) === true,
            'canCancel' => request()->user()?->can('cancel', $sale) === true,
            'canApproveDiscount' => request()->user()?->can('approveDiscount', $sale) === true,
        ]);
    }

    public function update(UpdateSaleDraftRequest $request, Sale $sale, UpdateSaleDraftAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $action->handle($sale, $user, $request->saleAttributes(), $request->itemsForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Venda atualizada com sucesso.']);

        return back();
    }

    public function confirm(ConfirmSaleRequest $request, Sale $sale, ConfirmSaleAction $action): RedirectResponse
    {
        $action->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Venda confirmada com sucesso.']);

        return back();
    }

    public function cancel(CancelSaleRequest $request, Sale $sale, CancelSaleAction $action): RedirectResponse
    {
        $action->handle($sale, (string) $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Venda cancelada com sucesso.']);

        return back();
    }

    public function approveDiscount(ApproveSaleItemDiscountRequest $request, Sale $sale, SaleItem $item, ApproveSaleItemDiscountAction $action): RedirectResponse
    {
        if ($item->sale_id !== $sale->id) {
            abort(404);
        }

        /** @var User $user */
        $user = Auth::user();

        $action->handle($item, $user, (string) $request->input('justification'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Desconto aprovado com sucesso.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function presentSale(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'status' => $sale->status->value,
            'status_label' => $sale->status->label(),
            'patient_name' => $sale->patient->preferred_name ?: $sale->patient->name,
            'patient_id' => $sale->patient_id,
            'unit_id' => $sale->unit_id,
            'unit_name' => $sale->unit->name,
            'legal_entity_id' => $sale->legal_entity_id,
            'legal_entity_name' => $sale->legalEntity->legal_name,
            'professional_id' => $sale->professional_id,
            'professional_name' => $sale->professional?->display_name,
            'subtotal_cents' => $sale->subtotal_cents,
            'discount_total_cents' => $sale->discount_total_cents,
            'total_cents' => $sale->total_cents,
            'cancellation_reason' => $sale->cancellation_reason,
            'created_at' => $sale->created_at->toIso8601String(),
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'id' => $item->id,
                'item_type' => $item->item_type->value,
                'item_type_label' => $item->item_type->label(),
                'label' => $this->itemLabel($item),
                'session_count' => $item->session_count,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'discount_percentage' => $item->discount_percentage,
                'final_price_cents' => $item->final_price_cents,
                'requires_approval' => $item->requires_approval,
                'is_pending_approval' => $item->isPendingApproval(),
                'approver_name' => $item->approver?->name,
                'approved_at' => $item->approved_at?->toIso8601String(),
                'approval_justification' => $item->approval_justification,
            ])->values(),
        ];
    }

    private function itemLabel(SaleItem $item): string
    {
        if ($item->service !== null) {
            return $item->service->name;
        }

        if ($item->product !== null) {
            return $item->product->name;
        }

        return '—';
    }
}
