<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalEntities\Pages;

use App\Actions\Organization\UpdateLegalEntityAction;
use App\Filament\Resources\LegalEntities\LegalEntityResource;
use App\Models\LegalEntity;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLegalEntity extends EditRecord
{
    protected static string $resource = LegalEntityResource::class;

    protected function getHeaderActions(): array
    {
        // Sem DeleteAction: nenhuma exclusão física de dados de negócio.
        return [
            ViewAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var LegalEntity $record */
        return app(UpdateLegalEntityAction::class)->handle($record, $data);
    }
}
