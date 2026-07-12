<?php

namespace App\Filament\Resources\OrganizationMemberships;

use App\Filament\Resources\OrganizationMemberships\Pages\EditOrganizationMembership;
use App\Filament\Resources\OrganizationMemberships\Pages\ListOrganizationMemberships;
use App\Filament\Resources\OrganizationMemberships\Pages\ViewOrganizationMembership;
use App\Filament\Resources\OrganizationMemberships\Schemas\OrganizationMembershipForm;
use App\Filament\Resources\OrganizationMemberships\Schemas\OrganizationMembershipInfolist;
use App\Filament\Resources\OrganizationMemberships\Tables\OrganizationMembershipsTable;
use App\Models\OrganizationMembership;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrganizationMembershipResource extends Resource
{
    protected static ?string $model = OrganizationMembership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OrganizationMembershipForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrganizationMembershipInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationMembershipsTable::configure($table);
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
            'index' => ListOrganizationMemberships::route('/'),
            'view' => ViewOrganizationMembership::route('/{record}'),
            'edit' => EditOrganizationMembership::route('/{record}/edit'),
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
