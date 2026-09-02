<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Professional;
use App\Support\Auditing\AuditLogger;
use App\Support\Site\SafeFileReplacer;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Substitui a foto do profissional usando o mesmo fluxo seguro já usado
 * para banner/logo/favicon do site e para a foto do próprio usuário
 * (App\Support\Site\SafeFileReplacer): nome de arquivo não previsível
 * (`store()` gera um nome aleatório), MIME/extensão validados no Form
 * Request, e o arquivo antigo só é removido depois que a nova referência
 * foi persistida com sucesso — uma falha nunca apaga a foto anterior.
 */
class UpdateProfessionalPhotoAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Professional $professional, UploadedFile $photo): Professional
    {
        $replacer = new SafeFileReplacer;
        $replacer->stage($professional, 'photo_path', $photo, 'professional-photos');

        try {
            $professional->save();
        } catch (Throwable $e) {
            $replacer->rollback();

            throw $e;
        }

        $replacer->commit();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $professional,
            after: ['photo_path' => 'updated'],
            organization: $professional->organization,
        );

        return $professional;
    }
}
