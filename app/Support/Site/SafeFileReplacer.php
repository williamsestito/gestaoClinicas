<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Substituição segura de um arquivo referenciado por uma coluna: o arquivo
 * novo é salvo e a coluna é atualizada em memória via stage(), sem tocar no
 * arquivo anterior. Só depois que o chamador confirma que a persistência
 * (`$model->save()`) teve sucesso — chamando commit() — os arquivos antigos,
 * agora órfãos, são removidos. Se a persistência falhar, o chamador deve
 * chamar rollback() para descartar os arquivos novos e preservar as
 * referências antigas intactas. Usado por hero_image/logo/favicon do
 * `SiteSetting` (ver Fase 0, Etapas 0.3 e 0.4).
 */
class SafeFileReplacer
{
    /** @var list<string> */
    private array $storedPaths = [];

    /** @var array<string, string|null> coluna => caminho anterior */
    private array $previousPaths = [];

    public function __construct(private readonly string $disk = 'public') {}

    /**
     * Salva o novo arquivo (se houver) e atualiza a coluna no model em
     * memória. Não apaga o arquivo anterior — isso só acontece em commit().
     */
    public function stage(Model $record, string $column, ?UploadedFile $file, string $directory): void
    {
        if (! $file) {
            return;
        }

        $path = $file->store($directory, $this->disk);

        if ($path === false) {
            $this->rollback();

            throw new RuntimeException("Falha ao salvar o arquivo enviado para \"{$column}\" no armazenamento.");
        }

        $this->previousPaths[$column] = $record->getAttribute($column);
        $this->storedPaths[] = $path;
        $record->setAttribute($column, $path);
    }

    /**
     * @return array<string, string|null> coluna => caminho anterior, apenas para as colunas efetivamente substituídas
     */
    public function previousPaths(): array
    {
        return $this->previousPaths;
    }

    /**
     * Chamado após a persistência ter tido sucesso: remove os arquivos
     * antigos, agora órfãos. Falha ao remover um arquivo antigo é
     * reportada, mas não interrompe o fluxo nem desfaz o arquivo novo.
     */
    public function commit(): void
    {
        foreach ($this->previousPaths as $oldPath) {
            if ($oldPath !== null && Storage::disk($this->disk)->exists($oldPath)) {
                try {
                    Storage::disk($this->disk)->delete($oldPath);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /**
     * Chamado quando a persistência falha: remove os arquivos novos para
     * não deixar órfãos, preservando as referências antigas intactas.
     */
    public function rollback(): void
    {
        foreach ($this->storedPaths as $path) {
            Storage::disk($this->disk)->delete($path);
        }
    }
}
