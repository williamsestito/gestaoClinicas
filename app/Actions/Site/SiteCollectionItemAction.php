<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Enums\AuditAction;
use App\Support\Auditing\AuditLogger;
use App\Support\Site\SafeFileReplacer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
    /**
     * Substituição de imagem em andamento nesta requisição (entre a
     * chamada a replaceImage() e a chamada a upsert() feitas pelo
     * Controller sobre a mesma instância injetada) — permite que upsert()
     * só apague o arquivo antigo depois que save() tiver sucesso.
     */
    private ?SafeFileReplacer $pendingFileReplacer = null;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(Model $record, array $data): Model
    {
        $isCreating = ! $record->exists;
        $before = $isCreating ? [] : $record->only(array_keys($data));

        if ($this->pendingFileReplacer !== null) {
            $before = [...$before, ...$this->pendingFileReplacer->previousPaths()];
        }

        $record->fill($data);

        if ($isCreating && $record->getAttribute('order') === null) {
            $record->setAttribute('order', ((int) $record->newQuery()->max('order')) + 1);
        }

        try {
            $record->save();
        } catch (Throwable $e) {
            $this->pendingFileReplacer?->rollback();
            $this->pendingFileReplacer = null;

            throw $e;
        }

        $this->pendingFileReplacer?->commit();
        $this->pendingFileReplacer = null;

        $this->auditLogger->log(
            $isCreating ? AuditAction::Created : AuditAction::Updated,
            auditable: $record,
            before: $before,
            after: $record->only(array_keys($before)),
        );

        return $record;
    }

    /**
     * Prepara a troca da imagem: salva o arquivo novo e atualiza a coluna
     * em memória, sem apagar o arquivo antigo — isso só acontece dentro de
     * upsert(), depois que save() confirma sucesso. Controllers sempre
     * chamam replaceImage() antes de upsert() sobre a mesma instância
     * injetada de SiteCollectionItemAction.
     */
    public function replaceImage(Model $record, string $column, ?UploadedFile $file, string $directory): void
    {
        if (! $file) {
            return;
        }

        $this->pendingFileReplacer ??= new SafeFileReplacer;
        $this->pendingFileReplacer->stage($record, $column, $file, $directory);
    }

    /**
     * @param  string|null  $imageColumn  coluna com o caminho da imagem do item, quando existir — o arquivo é removido do disco após a exclusão, para não deixar órfão
     */
    public function delete(Model $record, ?string $imageColumn = null): void
    {
        $this->auditLogger->log(
            AuditAction::Deleted,
            auditable: $record,
            before: $record->only($record->getFillable()),
        );

        $imagePath = $imageColumn ? $record->getAttribute($imageColumn) : null;

        $record->delete();

        if ($imagePath !== null && Storage::disk('public')->exists($imagePath)) {
            try {
                Storage::disk('public')->delete($imagePath);
            } catch (Throwable $e) {
                report($e);
            }
        }
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
