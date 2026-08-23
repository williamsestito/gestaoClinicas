<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Categorias de arquivo clínico (Seção 11.1 do documento de visão) — só as
 * clinicamente sensíveis entram nesta etapa; categorias puramente
 * administrativas (contratos, documentos pessoais, comprovantes) ficam de
 * fora, já que arquivos aqui herdam a mesma restrição de acesso de
 * `App\Policies\MedicalRecordPolicy`.
 */
enum MedicalRecordFileCategory: string
{
    case Exam = 'exam';
    case ClinicalPhoto = 'clinical_photo';
    case Prescription = 'prescription';
    case CertificateOrDeclaration = 'certificate_or_declaration';
    case Consent = 'consent';
    case Referral = 'referral';
    case Report = 'report';

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'Exame',
            self::ClinicalPhoto => 'Fotografia clínica',
            self::Prescription => 'Prescrição',
            self::CertificateOrDeclaration => 'Atestado ou declaração',
            self::Consent => 'Consentimento',
            self::Referral => 'Encaminhamento',
            self::Report => 'Laudo',
        };
    }
}
