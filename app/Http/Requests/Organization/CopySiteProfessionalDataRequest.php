<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\SiteSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CopySiteProfessionalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = app(TenantContext::class)->organization();

        return $organization !== null && $this->user()?->can('update', [SiteSetting::class, $organization]) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'in:name,bio'],
        ];
    }
}
