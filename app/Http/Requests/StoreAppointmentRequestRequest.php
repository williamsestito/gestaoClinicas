<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LegalEntityType;
use App\Models\Organization;
use App\Rules\CpfCnpjRule;
use App\Support\Documents\Document;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulário público de solicitação de agendamento (lead) na landing page.
 * Sem autenticação — qualquer visitante pode enviar. O backend é a única
 * fonte de verdade da validação e normalização (nunca confia em máscaras
 * ou formatação aplicadas só no frontend).
 */
class StoreAppointmentRequestRequest extends FormRequest
{
    private const PREFERRED_PERIODS = ['Manhã', 'Tarde', 'Noite'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->normalizePhone($this->string('phone')->toString()),
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'document' => $this->filled('document') ? Document::onlyDigits((string) $this->input('document')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('site_services', 'id')->where('is_active', true),
            ],
            // Instalação single-tenant nesta fase (ver
            // PublicSiteController::home()), mas a base de dados não impede
            // uma segunda Organization de existir (auto-onboarding) — sem
            // este escopo, um profissional de outra organização seria
            // aceito aqui e gravado junto com o organization_id resolvido
            // pelo Controller (App\Actions\Public\CreateAppointmentRequestAction).
            'professional_id' => [
                'nullable',
                'string',
                Rule::exists('professionals', 'id')
                    ->where('organization_id', Organization::query()->first()?->id)
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! preg_match('/^\(\d{2}\) \d{4,5}-\d{4}$/', (string) $value)) {
                        $fail('Informe um telefone válido com DDD.');
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255'],
            // Sem checagem de unicidade aqui de propósito: um lead público
            // não é um cadastro de paciente, só um indício para localizar um
            // já existente (ver CreateAppointmentRequestAction) — a mesma
            // pessoa pode enviar mais de uma solicitação ao longo do tempo.
            'document' => ['nullable', 'string', new CpfCnpjRule(LegalEntityType::Individual)],
            'preferred_period' => ['nullable', Rule::in(self::PREFERRED_PERIODS)],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(90)->toDateString()],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms_accepted' => ['required', 'accepted'],

            // Honeypot: nunca preenchido por um visitante real — validado
            // apenas para garantir que, se enviado, o request seja
            // rejeitado sem revelar a natureza do campo (ver Controller).
            'website' => ['nullable', 'string'],
            'form_rendered_at' => ['nullable', 'integer'],

            'utm' => ['nullable', 'array'],
            'utm.utm_source' => ['nullable', 'string', 'max:150'],
            'utm.utm_medium' => ['nullable', 'string', 'max:150'],
            'utm.utm_campaign' => ['nullable', 'string', 'max:150'],
            'utm.utm_content' => ['nullable', 'string', 'max:150'],
            'utm.utm_term' => ['nullable', 'string', 'max:150'],
            'utm.utm_id' => ['nullable', 'string', 'max:150'],
            'utm.utm_source_platform' => ['nullable', 'string', 'max:150'],
            'utm.ref' => ['nullable', 'string', 'max:150'],
            'utm.referrer' => ['nullable', 'string', 'max:500'],
            'utm.page_url' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Extrai só os dígitos e formata como "(DD) DDDDD-DDDD" (celular) ou
     * "(DD) DDDD-DDDD" (fixo). Remove o DDI 55 quando já informado, para
     * manter um formato local estável independente de como o visitante
     * digitou. Comprimentos fora do esperado são deixados para a regra de
     * validação do campo `phone` rejeitar com uma mensagem clara.
     */
    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return match (strlen($digits)) {
            11 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
            10 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
            default => $digits,
        };
    }
}
