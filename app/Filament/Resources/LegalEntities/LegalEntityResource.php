<?php

namespace App\Filament\Resources\LegalEntities;

use App\Filament\Resources\LegalEntities\Pages\EditLegalEntity;
use App\Filament\Resources\LegalEntities\Pages\ListLegalEntities;
use App\Filament\Resources\LegalEntities\Pages\ViewLegalEntity;
use App\Filament\Resources\LegalEntities\Schemas\LegalEntityForm;
use App\Filament\Resources\LegalEntities\Schemas\LegalEntityInfolist;
use App\Filament\Resources\LegalEntities\Tables\LegalEntitiesTable;
use App\Models\LegalEntity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LegalEntityResource extends Resource
{
    protected static ?string $model = LegalEntity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getModelLabel(): string
    {
        return 'Entidade legal';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Entidades legais';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Clínicas';
    }

    public static function form(Schema $schema): Schema
    {
        return LegalEntityForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LegalEntityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegalEntitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalEntities::route('/'),
            'view' => ViewLegalEntity::route('/{record}'),
            'edit' => EditLegalEntity::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
