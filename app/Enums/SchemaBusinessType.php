<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos Schema.org permitidos para o JSON-LD da página pública — lista
 * fechada e segura, para evitar que o administrador aplique um tipo
 * incorreto (ex.: "Dentist" para uma clínica sem essa especialidade).
 */
enum SchemaBusinessType: string
{
    case LocalBusiness = 'LocalBusiness';
    case MedicalClinic = 'MedicalClinic';
    case Dentist = 'Dentist';
    case Physician = 'Physician';
    case HealthAndBeautyBusiness = 'HealthAndBeautyBusiness';
    case ProfessionalService = 'ProfessionalService';

    public function label(): string
    {
        return match ($this) {
            self::LocalBusiness => 'Negócio local (genérico)',
            self::MedicalClinic => 'Clínica médica',
            self::Dentist => 'Consultório odontológico',
            self::Physician => 'Profissional médico',
            self::HealthAndBeautyBusiness => 'Estética e bem-estar',
            self::ProfessionalService => 'Serviço profissional',
        };
    }
}
