<?php

declare(strict_types=1);

namespace App\Filament\Resources\Units\Pages;

use App\Actions\Organization\UpdateUnitAction;
use App\Filament\Resources\Units\UnitResource;
use App\Models\Unit;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUnit extends EditRecord
{
    protected static string $resource = UnitResource::class;

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
        /** @var Unit $record */
        return app(UpdateUnitAction::class)->handle($record, $data);
    }
}
