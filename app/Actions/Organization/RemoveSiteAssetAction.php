<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Auditing\AuditLogger;
use App\Support\Site\FaviconGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Remoção de qualquer um dos arquivos institucionais do site (banner
 * desktop/mobile, logo, favicon): limpa a coluna, apaga o arquivo do disco
 * e audita a mudança. Reutilizada por todos porque a lógica é idêntica —
 * só a coluna e a mensagem de erro mudam. O favicon tem um passo extra
 * (`$variantsColumn`) para também apagar os tamanhos gerados.
 */
class RemoveSiteAssetAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FaviconGenerator $faviconGenerator,
    ) {}

    public function handle(
        ?SiteSetting $siteSetting,
        string $column,
        string $errorKey,
        string $missingContentMessage,
        ?Organization $organization = null,
        ?string $variantsColumn = null,
    ): SiteSetting {
        if (! $siteSetting) {
            throw ValidationException::withMessages([
                $errorKey => $missingContentMessage,
            ]);
        }

        $path = $siteSetting->getAttribute($column);
        $variants = $variantsColumn ? $siteSetting->getAttribute($variantsColumn) : null;

        if ($path === null && $variants === null) {
            return $siteSetting;
        }

        $update = [$column => null];
        if ($variantsColumn) {
            $update[$variantsColumn] = null;
        }

        $siteSetting->update($update);

        if ($path !== null && Storage::disk('public')->exists($path)) {
            try {
                Storage::disk('public')->delete($path);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($variants !== null) {
            $this->faviconGenerator->delete($variants);
        }

        $before = [$column => $path];
        if ($variantsColumn) {
            $before[$variantsColumn] = $variants;
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $siteSetting,
            before: $before,
            after: $update,
            organization: $organization,
        );

        return $siteSetting;
    }
}
