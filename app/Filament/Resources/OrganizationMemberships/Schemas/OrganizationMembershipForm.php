<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrganizationMemberships\Schemas;

use App\Enums\OrganizationMembershipStatus;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class OrganizationMembershipForm
{
    /**
     * Somente o status é editável aqui. Organização/usuário/proprietário são
     * definidos na criação do vínculo (onboarding) — transferência de
     * propriedade é um fluxo próprio, fora do escopo desta fase.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->options(OrganizationMembershipStatus::class)
                    ->required(),
            ]);
    }
}
