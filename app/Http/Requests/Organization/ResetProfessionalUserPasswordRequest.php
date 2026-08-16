<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Concerns\PasswordValidationRules;
use App\Models\Professional;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResetProfessionalUserPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('linkUser', $this->route('professional')) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->passwordRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Professional|null $professional */
            $professional = $this->route('professional');

            if ($professional?->user_id === null) {
                $validator->errors()->add('password', 'Este profissional não tem um usuário de acesso vinculado.');
            }
        });
    }
}
