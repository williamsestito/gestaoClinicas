<?php

declare(strict_types=1);

namespace App\Filament\Resources\Specialties;

use App\Filament\Resources\Specialties\Pages\EditSpecialty;
use App\Filament\Resources\Specialties\Pages\ListSpecialties;
use App\Filament\Resources\Specialties\Pages\ViewSpecialty;
use App\Filament\Resources\Specialties\Schemas\SpecialtyForm;
use App\Filament\Resources\Specialties\Schemas\SpecialtyInfolist;
use App\Filament\Resources\Specialties\Tables\SpecialtiesTable;
use App\Models\Specialty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SpecialtyResource extends Resource
{
    protected static ?string $model = Specialty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getModelLabel(): string
    {
        return 'Especialidade';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Especialidades';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Equipe e serviços';
    }

    public static function form(Schema $schema): Schema
    {
        return SpecialtyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SpecialtyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpecialtiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpecialties::route('/'),
            'view' => ViewSpecialty::route('/{record}'),
            'edit' => EditSpecialty::route('/{record}/edit'),
        ];
    }
}
