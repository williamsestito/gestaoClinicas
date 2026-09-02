<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Auditing\AuditLogger;
use App\Support\Site\FaviconGenerator;
use App\Support\Site\SafeFileReplacer;
use Illuminate\Http\UploadedFile;
use Throwable;

class UpdateSiteContentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FaviconGenerator $faviconGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array{hero_image?: UploadedFile|null, hero_image_mobile?: UploadedFile|null, logo?: UploadedFile|null, favicon?: UploadedFile|null}  $files
     */
    public function handle(?SiteSetting $siteSetting, array $data, array $files, ?Organization $organization = null): SiteSetting
    {
        $record = $siteSetting ?? new SiteSetting;
        $before = $record->exists ? $record->only(array_keys($data)) : [];

        // Os arquivos institucionais (banner desktop/mobile, logo, favicon)
        // usam a mesma sequência segura: o arquivo novo é salvo e a coluna
        // atualizada em memória (stage), o registro é persistido, e só
        // então o arquivo antigo é removido (commit) — nunca o contrário,
        // para nunca perder a referência ao arquivo atual se o save()
        // falhar (ver Fase 0, Etapas 0.3 e 0.4).
        $replacer = new SafeFileReplacer;
        $replacer->stage($record, 'hero_image_path', $files['hero_image'] ?? null, 'site-content');
        $replacer->stage($record, 'hero_image_mobile_path', $files['hero_image_mobile'] ?? null, 'site-content');
        $replacer->stage($record, 'logo_path', $files['logo'] ?? null, 'site-content');
        $replacer->stage($record, 'favicon_path', $files['favicon'] ?? null, 'site-content');

        $before = [...$before, ...$replacer->previousPaths()];

        // O favicon segue o mesmo princípio (gera o novo antes de tocar no
        // registro, só apaga o antigo depois do save() confirmado), mas
        // precisa de um passo extra: os tamanhos são gerados a partir do
        // arquivo original (não apenas copiados), então ficam numa coluna
        // JSON própria em vez de reaproveitar o `favicon_path` bruto.
        $previousFaviconVariants = $record->favicon_variants;
        $newFaviconVariants = null;

        if ($files['favicon'] ?? null) {
            $newFaviconVariants = $this->faviconGenerator->generate($files['favicon']);
            $record->favicon_variants = $newFaviconVariants;
            $before['favicon_variants'] = $previousFaviconVariants;
        }

        $record->fill($data);

        try {
            $record->save();
        } catch (Throwable $e) {
            $replacer->rollback();
            $this->faviconGenerator->delete($newFaviconVariants);

            throw $e;
        }

        $replacer->commit();

        if ($newFaviconVariants !== null) {
            $this->faviconGenerator->delete($previousFaviconVariants);
        }

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
