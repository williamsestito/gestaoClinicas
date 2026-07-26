<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Remoção de qualquer um dos três arquivos institucionais do site
 * (hero_image_path, logo_path, favicon_path): limpa a coluna, apaga o
 * arquivo do disco e audita a mudança. Reutilizada pelos três porque a
 * lógica é idêntica — só a coluna e a mensagem de erro mudam.
 */
class RemoveSiteAssetAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(
        ?SiteSetting $siteSetting,
        string $column,
        string $errorKey,
        string $missingContentMessage,
        ?Organization $organization = null,
    ): SiteSetting {
        if (! $siteSetting) {
            throw ValidationException::withMessages([
                $errorKey => $missingContentMessage,
            ]);
        }

        $path = $siteSetting->getAttribute($column);

        if ($path === null) {
            return $siteSetting;
        }

        $siteSetting->update([$column => null]);

        if (Storage::disk('public')->exists($path)) {
            try {
                Storage::disk('public')->delete($path);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $siteSetting,
            before: [$column => $path],
            after: [$column => null],
            organization: $organization,
        );

        return $siteSetting;
    }
}
