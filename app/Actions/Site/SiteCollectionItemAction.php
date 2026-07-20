<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Enums\AuditAction;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ciclo de vida compartilhado dos itens de coleção da landing pública
 * (benefícios, serviços, profissionais, galeria, depoimentos, FAQ) — todos
 * têm o mesmo formato (`order` + `is_active`), então a lógica de
 * criar/editar/excluir/reordenar/ativar fica aqui em vez de duplicada em
 * cada Action específica. Os Controllers e FormRequests continuam
 * específicos por recurso (tipagem e validação próprias).
 */
class SiteCollectionItemAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(Model $record, array $data): Model
    {
        $isCreating = ! $record->exists;
        $before = $isCreating ? [] : $record->only(array_keys($data));

        $record->fill($data);

        if ($isCreating && $record->getAttribute('order') === null) {
            $record->setAttribute('order', ((int) $record->newQuery()->max('order')) + 1);
        }

        $record->save();

        $this->auditLogger->log(
            $isCreating ? AuditAction::Created : AuditAction::Updated,
            auditable: $record,
            before: $before,
            after: $record->only(array_keys($data)),
        );

        return $record;
    }

    public function replaceImage(Model $record, string $column, ?UploadedFile $file, string $directory): void
    {
        if (! $file) {
            return;
        }

        $existingPath = $record->getAttribute($column);

        if ($existingPath && Storage::disk('public')->exists($existingPath)) {
            Storage::disk('public')->delete($existingPath);
        }

        $record->setAttribute($column, $file->store($directory, 'public'));
    }

    public function delete(Model $record): void
    {
        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $record,
            before: $record->only($record->getFillable()),
        );

        $record->delete();
    }

    public function toggleActive(Model $record): Model
    {
        $before = $record->only(['is_active']);
        $record->setAttribute('is_active', ! $record->getAttribute('is_active'));
        $record->save();

        $this->auditLogger->log(
            $record->getAttribute('is_active') ? AuditAction::Activated : AuditAction::Deactivated,
            auditable: $record,
            before: $before,
            after: $record->only(['is_active']),
        );

        return $record;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<int|string>  $orderedIds
     */
    public function reorder(string $modelClass, array $orderedIds): void
    {
        DB::transaction(function () use ($modelClass, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                $modelClass::query()->whereKey($id)->update(['order' => $index]);
            }
        });
    }
}
