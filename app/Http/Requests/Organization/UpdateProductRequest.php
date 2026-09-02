<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => CreateSpecialtyRequest::normalizeSpaces($this->input('name')),
            'code' => CreateSpecialtyRequest::normalizeCode($this->input('code')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $organizationId = $product?->organization_id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('products', 'code')->where('organization_id', $organizationId)->ignore($product?->id),
            ],
            'barcode' => ['nullable', 'string', 'max:64'],
            'unit_of_measure' => ['required', 'string', 'max:10'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'margin_percentage' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Já existe um produto com este código.',
            'code.regex' => 'O código deve conter apenas letras maiúsculas, números e hífen.',
        ];
    }

    /** @return array{name: string, code: string, barcode: ?string, unit_of_measure: string, cost_cents: ?int, margin_percentage: ?int, price_cents: ?int, max_discount_percentage: ?int, internal_notes: ?string} */
    public function attributesForAction(): array
    {
        $cost = $this->input('cost');
        $price = $this->input('price');

        return [
            'name' => (string) $this->input('name'),
            'code' => (string) $this->input('code'),
            'barcode' => $this->input('barcode'),
            'unit_of_measure' => (string) $this->input('unit_of_measure'),
            'cost_cents' => $cost === null || $cost === '' ? null : (int) round(((float) $cost) * 100),
            'margin_percentage' => $this->input('margin_percentage') === null || $this->input('margin_percentage') === '' ? null : (int) $this->input('margin_percentage'),
            'price_cents' => $price === null || $price === '' ? null : (int) round(((float) $price) * 100),
            'max_discount_percentage' => $this->input('max_discount_percentage') === null || $this->input('max_discount_percentage') === '' ? null : (int) $this->input('max_discount_percentage'),
            'internal_notes' => $this->input('internal_notes'),
        ];
    }
}
