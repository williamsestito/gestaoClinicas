<?php

declare(strict_types=1);

namespace App\Filament\Resources\Specialties\Pages;

use App\Actions\Organization\UpdateSpecialtyAction;
use App\Filament\Resources\Specialties\SpecialtyResource;
use App\Models\Specialty;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSpecialty extends EditRecord
{
    protected static string $resource = SpecialtyResource::class;

    protected function getHeaderActions(): array
    {
        // Sem DeleteAction nativo: exclusão lógica acontece pela ação
        // "Excluir" da tabela, que chama DeleteSpecialtyAction.
        return [
            ViewAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Specialty $record */
        return app(UpdateSpecialtyAction::class)->handle($record, $data);
    }
}
