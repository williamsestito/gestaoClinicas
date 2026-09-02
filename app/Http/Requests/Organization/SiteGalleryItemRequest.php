<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\SiteSetting;
use App\Rules\ValidImageContentRule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteGalleryItemRequest extends FormRequest
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
            'image' => [Rule::requiredIf(fn () => $this->route('siteGalleryItem') === null), 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096', new ValidImageContentRule],
            'caption' => ['nullable', 'string', 'max:150'],
            'alt_text' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_cover' => ['boolean'],
        ];
    }
}
