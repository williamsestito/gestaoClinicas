<?php

namespace App\Filament\Resources\OrganizationMemberships;

use App\Filament\Resources\OrganizationMemberships\Pages\ListOrganizationMemberships;
use App\Filament\Resources\OrganizationMemberships\Pages\ViewOrganizationMembership;
use App\Filament\Resources\OrganizationMemberships\Schemas\OrganizationMembershipInfolist;
use App\Filament\Resources\OrganizationMemberships\Tables\OrganizationMembershipsTable;
use App\Models\OrganizationMembership;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Sem página de edição: o status de um vínculo só muda através das Actions
 * de domínio (ActivateOrganizationMembershipAction/
 * DeactivateOrganizationMembershipAction — ver ações da tabela), que
 * aplicam a regra "a organização sempre precisa de um proprietário ativo".
 * Editar o campo diretamente contornaria essa regra.
 */
class OrganizationMembershipResource extends Resource
{
    protected static ?string $model = OrganizationMembership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getModelLabel(): string
    {
        return 'Vínculo com a clínica';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Vínculos com a clínica';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Clínicas';
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

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
