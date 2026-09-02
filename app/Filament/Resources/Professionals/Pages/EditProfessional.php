<?php

declare(strict_types=1);

namespace App\Filament\Resources\Professionals\Pages;

use App\Actions\Organization\UpdateProfessionalAction;
use App\Filament\Resources\Professionals\ProfessionalResource;
use App\Models\Professional;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProfessional extends EditRecord
{
    protected static string $resource = ProfessionalResource::class;

    protected function getHeaderActions(): array
    {
        // Sem DeleteAction nativo: exclusão lógica acontece pela ação
        // "Excluir" da tabela, que chama DeleteProfessionalAction.
        return [
            ViewAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Professional $record */
        return app(UpdateProfessionalAction::class)->handle($record, $data);
    }
}
