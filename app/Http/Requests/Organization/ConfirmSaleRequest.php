<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirm', $this->route('sale')) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
