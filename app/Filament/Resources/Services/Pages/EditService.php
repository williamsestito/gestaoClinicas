<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services\Pages;

use App\Actions\Organization\UpdateServiceAction;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        // Sem DeleteAction nativo: exclusão lógica acontece pela ação
        // "Excluir" da tabela, que chama DeleteServiceAction.
        return [
            ViewAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Service $record */
        $attributes = [
            'name' => (string) $data['name'],
            'code' => (string) $data['code'],
            'description' => $data['description'] !== null ? (string) $data['description'] : null,
            'default_duration_minutes' => (int) $data['default_duration_minutes'],
            'buffer_before_minutes' => (int) ($data['buffer_before_minutes'] ?? 0),
            'buffer_after_minutes' => (int) ($data['buffer_after_minutes'] ?? 0),
            'default_price_cents' => $data['default_price'] !== null && $data['default_price'] !== ''
                ? (int) round(((float) $data['default_price']) * 100)
                : null,
            'cost_cents' => ($data['cost'] ?? null) !== null && $data['cost'] !== ''
                ? (int) round(((float) $data['cost']) * 100)
                : null,
            'margin_percentage' => ($data['margin_percentage'] ?? null) !== null && $data['margin_percentage'] !== ''
                ? (int) $data['margin_percentage']
                : null,
            'max_discount_percentage' => ($data['max_discount_percentage'] ?? null) !== null && $data['max_discount_percentage'] !== ''
                ? (int) $data['max_discount_percentage']
                : null,
            'color' => $data['color'] !== null ? (string) $data['color'] : null,
            'is_public' => (bool) ($data['is_public'] ?? false),
            'requires_manual_confirmation' => (bool) ($data['requires_manual_confirmation'] ?? false),
            'internal_notes' => $data['internal_notes'] !== null ? (string) $data['internal_notes'] : null,
            'unit_availability_scope' => (string) $data['unit_availability_scope'],
            'specialty_ids' => $data['specialty_ids'] ?? [],
            'unit_ids' => $data['unit_ids'] ?? [],
        ];

        return app(UpdateServiceAction::class)->handle($record, $attributes);
    }
}
