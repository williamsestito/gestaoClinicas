<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\SaleItemType;
use App\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('create', [Sale::class, $organization]) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->organization()?->id;

        return [
            'unit_id' => ['required', 'string', Rule::exists('units', 'id')->where('organization_id', $organizationId)],
            'legal_entity_id' => ['required', 'string', Rule::exists('legal_entities', 'id')->where('organization_id', $organizationId)],
            'patient_id' => ['required', 'string', Rule::exists('patients', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'professional_id' => ['nullable', 'string', Rule::exists('professionals', 'id')->where('organization_id', $organizationId)],
            'appointment_id' => ['nullable', 'string', Rule::exists('appointments', 'id')->where('organization_id', $organizationId)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', Rule::enum(SaleItemType::class)],
            'items.*.service_id' => ['nullable', 'string', Rule::exists('services', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'items.*.product_id' => ['nullable', 'string', Rule::exists('products', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'items.*.session_count' => ['nullable', 'integer', 'min:1', 'max:365'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.discount_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Selecione o paciente para continuar.',
            'items.required' => 'A venda precisa de pelo menos um item.',
            'items.*.service_id.exists' => 'Um dos serviços selecionados não pertence a esta clínica ou não está disponível.',
            'items.*.product_id.exists' => 'Um dos produtos selecionados não pertence a esta clínica ou não está disponível.',
        ];
    }

    /** @return array{unit_id: string, legal_entity_id: string, patient_id: string, professional_id: ?string, appointment_id: ?string} */
    public function saleAttributes(): array
    {
        return [
            'unit_id' => (string) $this->input('unit_id'),
            'legal_entity_id' => (string) $this->input('legal_entity_id'),
            'patient_id' => (string) $this->input('patient_id'),
            'professional_id' => $this->input('professional_id'),
            'appointment_id' => $this->input('appointment_id'),
        ];
    }

    /** @return array<int, array{item_type: string, service_id: ?string, product_id: ?string, session_count: ?int, quantity: int, discount_percentage: int, unit_price_cents: ?int}> */
    public function itemsForAction(): array
    {
        return array_map(function (array $item) {
            $unitPrice = $item['unit_price'] ?? null;

            return [
                'item_type' => (string) $item['item_type'],
                'service_id' => $item['service_id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'session_count' => isset($item['session_count']) ? (int) $item['session_count'] : null,
                'quantity' => (int) $item['quantity'],
                'discount_percentage' => (int) $item['discount_percentage'],
                'unit_price_cents' => $unitPrice === null || $unitPrice === '' ? null : (int) round(((float) $unitPrice) * 100),
            ];
        }, (array) $this->input('items', []));
    }
}
