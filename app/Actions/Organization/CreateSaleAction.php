<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\PermissionKey;
use App\Enums\RecordStatus;
use App\Enums\SaleItemType;
use App\Enums\SaleStatus;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSaleAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionChecker $permissionChecker,
    ) {}

    /**
     * @param  array{unit_id: string, legal_entity_id: string, patient_id: string, professional_id: ?string, appointment_id: ?string}  $attributes
     * @param  array<int, array{item_type: string, service_id: ?string, product_id: ?string, session_count: ?int, quantity: int, discount_percentage: int, unit_price_cents: ?int}>  $items
     */
    public function handle(Organization $organization, User $creator, array $attributes, array $items): Sale
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'A venda precisa de pelo menos um item.',
            ]);
        }

        $this->assertPatientAccessible($creator, $organization->id, $attributes['patient_id'], $attributes['professional_id']);

        return DB::transaction(function () use ($organization, $creator, $attributes, $items) {
            $sale = $organization->sales()->create([
                ...$attributes,
                'status' => SaleStatus::Draft,
                'created_by' => $creator->id,
            ]);

            foreach ($items as $item) {
                $this->createItem($sale, $item);
            }

            $this->recalculateTotals($sale);

            $this->auditLogger->log(
                AuditAction::Created,
                auditable: $sale,
                after: [
                    'patient_id' => $sale->patient_id,
                    'unit_id' => $sale->unit_id,
                    'status' => $sale->status->value,
                    'total_cents' => $sale->total_cents,
                    'item_count' => count($items),
                ],
                organization: $organization,
            );

            return $sale;
        });
    }

    /**
     * Cria um item sobre uma venda já existente — usado tanto na criação
     * quanto por `UpdateSaleDraftAction` ao recriar os itens de um
     * rascunho editado.
     *
     * @param  array{item_type: string, service_id: ?string, product_id: ?string, session_count: ?int, quantity: int, discount_percentage: int, unit_price_cents: ?int}  $item
     */
    public function createItem(Sale $sale, array $item): void
    {
        $type = SaleItemType::from($item['item_type']);
        $quantity = $item['quantity'];
        $discountPercentage = $item['discount_percentage'];

        [$unitPriceCents, $maxDiscountPercentage] = match ($type) {
            SaleItemType::Service => $this->resolveServicePricing($item, requireExplicitPrice: false),
            SaleItemType::ServicePackage => $this->resolveServicePricing($item, requireExplicitPrice: true),
            SaleItemType::Product => $this->resolveProductPricing($item),
        };

        $finalPriceCents = (int) round($quantity * $unitPriceCents * (1 - $discountPercentage / 100));
        $requiresApproval = $discountPercentage > ($maxDiscountPercentage ?? 0);

        $sale->items()->create([
            'organization_id' => $sale->organization_id,
            'item_type' => $type,
            'service_id' => $item['service_id'] ?? null,
            'product_id' => $item['product_id'] ?? null,
            'session_count' => $item['session_count'] ?? null,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
            'discount_percentage' => $discountPercentage,
            'final_price_cents' => $finalPriceCents,
            'requires_approval' => $requiresApproval,
        ]);
    }

    /**
     * @param  array{service_id: ?string, unit_price_cents: ?int}  $item
     * @return array{0: int, 1: ?int}
     */
    private function resolveServicePricing(array $item, bool $requireExplicitPrice): array
    {
        if (empty($item['service_id'])) {
            throw ValidationException::withMessages(['items' => 'Informe o serviço do item.']);
        }

        $service = Service::query()
            ->where('id', $item['service_id'])
            ->firstOrFail();

        if ($requireExplicitPrice) {
            if (empty($item['unit_price_cents'])) {
                throw ValidationException::withMessages(['items' => 'Informe o preço total do pacote de sessões.']);
            }

            return [$item['unit_price_cents'], $service->max_discount_percentage];
        }

        // O preço de catálogo sempre prevalece quando existe — o cliente
        // nunca pode sobrescrevê-lo diretamente. `unit_price_cents`
        // informado só é usado quando o serviço não tem preço cadastrado.
        // Sem isso, qualquer redução de preço poderia ser feita fora do
        // campo `discount_percentage`, contornando por completo o limite
        // de desconto e a aprovação (RN-010/RN-011).
        $unitPriceCents = $service->default_price_cents ?? $item['unit_price_cents'] ?? null;

        if ($unitPriceCents === null) {
            throw ValidationException::withMessages(['items' => 'Este serviço não tem preço definido — informe um preço para o item.']);
        }

        return [$unitPriceCents, $service->max_discount_percentage];
    }

    /**
     * @param  array{product_id: ?string, unit_price_cents: ?int}  $item
     * @return array{0: int, 1: ?int}
     */
    private function resolveProductPricing(array $item): array
    {
        if (empty($item['product_id'])) {
            throw ValidationException::withMessages(['items' => 'Informe o produto do item.']);
        }

        $product = Product::query()
            ->where('id', $item['product_id'])
            ->firstOrFail();

        // Mesma regra do serviço: o preço de catálogo sempre prevalece.
        $unitPriceCents = $product->price_cents ?? $item['unit_price_cents'] ?? null;

        if ($unitPriceCents === null) {
            throw ValidationException::withMessages(['items' => 'Este produto não tem preço definido — informe um preço para o item.']);
        }

        return [$unitPriceCents, $product->max_discount_percentage];
    }

    public function recalculateTotals(Sale $sale): void
    {
        $sale->load('items');

        $subtotalCents = 0;
        $totalCents = 0;

        foreach ($sale->items as $item) {
            $subtotalCents += $item->quantity * $item->unit_price_cents;
            $totalCents += $item->final_price_cents;
        }

        $sale->update([
            'subtotal_cents' => $subtotalCents,
            'discount_total_cents' => max($subtotalCents - $totalCents, 0),
            'total_cents' => $totalCents,
            'status' => $sale->hasPendingApprovalItems() ? SaleStatus::PendingApproval : $sale->status,
        ]);
    }

    /**
     * `SalePolicy::create()` só sabe checar a permissão em nível de
     * organização (o paciente ainda não existe como recurso no momento da
     * checagem) — sem isto, um usuário que só tem `SalesManageOwn`
     * (ex.: profissional) conseguiria criar uma venda para o paciente de
     * um colega só por ter a permissão "própria" concedida. Mesmo padrão
     * de segunda checagem já usado em `AppointmentController::store()`
     * para `createFromOwnRequest`. Também garante que `professional_id`,
     * quando informado, corresponde ao próprio profissional vinculado ao
     * usuário — sem isso, um profissional autorizado só pela via "própria"
     * poderia atribuir a venda a um colega.
     */
    public function assertPatientAccessible(User $creator, string $organizationId, string $patientId, ?string $professionalId): void
    {
        if ($this->permissionChecker->can($creator, PermissionKey::SalesManage, $organizationId)) {
            return;
        }

        $patient = Patient::query()->where('organization_id', $organizationId)->find($patientId);

        $isOwnPatient = $patient !== null
            && $patient->primary_professional_id !== null
            && $creator->professionals()
                ->where('status', RecordStatus::Active)
                ->whereKey($patient->primary_professional_id)
                ->exists();

        if (! $isOwnPatient) {
            throw ValidationException::withMessages([
                'patient_id' => 'Você só pode criar vendas para os seus próprios pacientes.',
            ]);
        }

        if ($professionalId !== null
            && ! $creator->professionals()->where('status', RecordStatus::Active)->whereKey($professionalId)->exists()
        ) {
            throw ValidationException::withMessages([
                'professional_id' => 'Você só pode atribuir a venda ao seu próprio cadastro de profissional.',
            ]);
        }
    }
}
