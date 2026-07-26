<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\SiteSetting;
use App\Rules\ValidImageContentRule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('update', [SiteSetting::class, $organization]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', new ValidImageContentRule],
            'icon' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', 'string', 'max:100'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'starting_price' => ['nullable', 'numeric', 'min:0'],
            'cta_text' => ['nullable', 'string', 'max:60'],
            'is_featured' => ['boolean'],
        ];
    }
}
