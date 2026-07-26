<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Auditing\AuditLogger;
use App\Support\Site\SafeFileReplacer;
use Illuminate\Http\UploadedFile;
use Throwable;

class UpdateSiteContentAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array{hero_image?: UploadedFile|null, logo?: UploadedFile|null, favicon?: UploadedFile|null}  $files
     */
    public function handle(?SiteSetting $siteSetting, array $data, array $files, ?Organization $organization = null): SiteSetting
    {
        $record = $siteSetting ?? new SiteSetting;
        $before = $record->exists ? $record->only(array_keys($data)) : [];

        // Os três arquivos institucionais (banner, logo, favicon) usam a
        // mesma sequência segura: o arquivo novo é salvo e a coluna
        // atualizada em memória (stage), o registro é persistido, e só
        // então o arquivo antigo é removido (commit) — nunca o contrário,
        // para nunca perder a referência ao arquivo atual se o save()
        // falhar (ver Fase 0, Etapas 0.3 e 0.4).
        $replacer = new SafeFileReplacer;
        $replacer->stage($record, 'hero_image_path', $files['hero_image'] ?? null, 'site-content');
        $replacer->stage($record, 'logo_path', $files['logo'] ?? null, 'site-content');
        $replacer->stage($record, 'favicon_path', $files['favicon'] ?? null, 'site-content');

        $before = [...$before, ...$replacer->previousPaths()];

        $record->fill($data);

        try {
            $record->save();
        } catch (Throwable $e) {
            $replacer->rollback();

            throw $e;
        }

        $replacer->commit();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $record,
            before: $before,
            after: $record->only(array_keys($before)),
            organization: $organization,
        );

        return $record;
    }
}
