<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Pages;

use App\Actions\Organization\UpdateOrganizationAction;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        // Sem DeleteAction: organizações nunca são excluídas fisicamente.
        return [
            ViewAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Organization $record */
        return app(UpdateOrganizationAction::class)->handle($record, $data);
    }
}
