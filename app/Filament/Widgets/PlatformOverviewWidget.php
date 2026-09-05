<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Visão geral da plataforma no dashboard padrão do Filament (auto-descoberto
 * por App\Providers\Filament\AdminPanelProvider::discoverWidgets()).
 */
class PlatformOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $withoutActiveOwnerCount = Organization::query()->withoutActiveOwner()->count();

        return [
            Stat::make('Organizações', (string) Organization::query()->count()),
            Stat::make('Organizações ativas', (string) Organization::query()->where('status', OrganizationStatus::Active)->count()),
            Stat::make('Organizações inativas/suspensas', (string) Organization::query()->where('status', '!=', OrganizationStatus::Active)->count()),
            Stat::make('Usuários', (string) User::query()->count()),
            Stat::make('Sem administrador', (string) $withoutActiveOwnerCount)
                ->color($withoutActiveOwnerCount > 0 ? 'danger' : 'success'),
        ];
    }
}
