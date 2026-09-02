<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteBenefitRequest extends FormRequest
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
            'icon' => ['nullable', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
