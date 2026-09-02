<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Models\SiteSetting;
use App\Rules\ValidImageContentRule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteContentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'hero_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', new ValidImageContentRule],
            'hero_image_mobile' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', new ValidImageContentRule],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:1024', new ValidImageContentRule],
            'favicon' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:256', new ValidImageContentRule],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'cta_text' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'url:http,https', 'max:255'],
            'cta_secondary_text' => ['nullable', 'string', 'max:60'],
            'cta_secondary_url' => ['nullable', 'url:http,https', 'max:255'],
            'about_text' => ['nullable', 'string', 'max:2000'],
            'mission_text' => ['nullable', 'string', 'max:600'],
            'vision_text' => ['nullable', 'string', 'max:600'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
