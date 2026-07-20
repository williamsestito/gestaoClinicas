<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\SiteSetting;
use App\Support\Auditing\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

        if ($files['hero_image'] ?? null) {
            $this->replaceFile($record, 'hero_image_path', $files['hero_image']);
        }

        if ($files['logo'] ?? null) {
            $this->replaceFile($record, 'logo_path', $files['logo']);
        }

        if ($files['favicon'] ?? null) {
            $this->replaceFile($record, 'favicon_path', $files['favicon']);
        }

        $record->fill($data);
        $record->save();

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $record,
            before: $before,
            after: $record->only(array_keys($data)),
            organization: $organization,
        );

        return $record;
    }

    private function replaceFile(SiteSetting $record, string $column, UploadedFile $file): void
    {
        $existingPath = $record->getAttribute($column);

        if ($existingPath && Storage::disk('public')->exists($existingPath)) {
            Storage::disk('public')->delete($existingPath);
        }

        $record->setAttribute($column, $file->store('site-content', 'public'));
    }
}
