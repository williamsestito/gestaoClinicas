<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * RN-010: "aprovações serão realizadas pelo próprio usuário autorizador,
 * com senha, PIN individual, biometria ou segundo fator". Pedimos a senha
 * diretamente neste formulário (verificada em `withValidator()`) em vez
 * de depender do middleware `password.confirm` do Fortify — este é um
 * PATCH via Inertia, não uma navegação de página, e o fluxo de
 * redirecionamento do Fortify assume uma requisição GET.
 */
class ApproveSaleItemDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approveDiscount', $this->route('sale')) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:5', 'max:1000'],
            'password' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = Auth::user();

            if ($user === null || ! Hash::check((string) $this->input('password'), $user->password)) {
                $validator->errors()->add('password', 'Senha incorreta.');
            }
        });
    }
}
