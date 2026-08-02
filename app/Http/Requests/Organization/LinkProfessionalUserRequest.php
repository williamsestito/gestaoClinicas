<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Enums\OrganizationMembershipStatus;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LinkProfessionalUserRequest extends FormRequest
{
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Professional|null $professional */
            $professional = $this->route('professional');
            $organizationId = $professional?->organization_id;

            $hasActiveMembership = OrganizationMembership::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $this->input('user_id'))
                ->where('status', OrganizationMembershipStatus::Active)
                ->exists();

            if (! $hasActiveMembership) {
                $validator->errors()->add('user_id', 'O usuário selecionado não possui vínculo ativo com esta clínica.');

                return;
            }

            $alreadyLinked = Professional::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $this->input('user_id'))
                ->where('id', '!=', $professional?->id)
                ->exists();

            if ($alreadyLinked) {
                $validator->errors()->add('user_id', 'Este usuário já está vinculado a outro profissional nesta clínica.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'Selecione um usuário válido.',
        ];
    }
}
