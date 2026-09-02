<?php

declare(strict_types=1);

namespace App\Http\Requests\PatientPortal;

use App\Rules\ValidImageContentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mesmo padrão do resto do portal: `authorize(): true` — o vínculo real é
 * checado no controller via `$patientUser->patients()->findOrFail($patient)`
 * (ver App\Http\Requests\PatientPortal\UpdatePatientPortalProfileRequest).
 * Mesmas regras de validação do equivalente administrativo
 * (App\Http\Requests\Organization\UpdatePatientPhotoRequest).
 */
class UpdatePatientPortalPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', new ValidImageContentRule],
        ];
    }
}
