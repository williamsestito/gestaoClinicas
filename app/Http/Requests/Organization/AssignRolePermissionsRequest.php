<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\PermissionKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignPermissions', $this->route('role')) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(array_map(fn (PermissionKey $key) => $key->value, PermissionKey::cases()))],
        ];
    }
}
