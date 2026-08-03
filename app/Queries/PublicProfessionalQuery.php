<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\RecordStatus;
use App\Models\Organization;
use App\Models\Professional;
use App\Models\SiteProfessional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Fonte única e normalizada de profissionais para exibição pública —
 * usada tanto pela seção "Equipe" da landing quanto pelo filtro de
 * profissional da busca de disponibilidade pública. Combina, sem
 * duplicar:
 *
 *   1. `SiteProfessional` ativos (dados promocionais têm prioridade;
 *      quando vinculados a um `Professional`, a elegibilidade do
 *      operacional é revalidada — nunca publica um vínculo para um
 *      profissional inativo/excluído/de outra organização);
 *   2. `Professional` operacionais com `is_public = true`, ativos, com
 *      unidade e especialidade/serviço ativos, que ainda NÃO têm um
 *      `SiteProfessional` vinculado (evita qualquer duplicidade — a
 *      deduplicação é sempre pelo vínculo explícito, nunca por nome).
 *
 * Nunca retorna Models completos nem dados sensíveis (documento, e-mail
 * privado, telefone privado, `user_id`, jornada, ausências).
 */
final class PublicProfessionalQuery
{
    /** @return Collection<int, array<string, mixed>> */
    public function forOrganization(Organization $organization): Collection
    {
        $siteProfessionals = SiteProfessional::query()
            ->where('is_active', true)
            ->with(['professional' => fn ($query) => $query->withTrashed()->select(['id', 'organization_id', 'status', 'deleted_at'])])
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->filter(fn (SiteProfessional $siteProfessional) => $this->isLinkedProfessionalEligible($siteProfessional->professional, $organization))
            ->map(fn (SiteProfessional $siteProfessional) => $this->mapSiteProfessional($siteProfessional));

        $linkedProfessionalIds = SiteProfessional::query()
            ->whereNotNull('professional_id')
            ->pluck('professional_id');

        $operationalOnly = $organization->professionals()
            ->where('status', RecordStatus::Active)
            ->where('is_public', true)
            ->whereNotIn('id', $linkedProfessionalIds)
            ->with([
                'unitLinks' => fn ($query) => $query->where('status', RecordStatus::Active)->whereHas('unit', fn ($unitQuery) => $unitQuery->where('status', RecordStatus::Active)),
                'specialtyLinks' => fn ($query) => $query->where('status', RecordStatus::Active)->with('specialty:id,name'),
                'serviceLinks' => fn ($query) => $query->where('status', RecordStatus::Active),
            ])
            ->orderBy('display_name')
            ->get()
            ->filter(fn (Professional $professional) => $professional->unitLinks->isNotEmpty()
                && ($professional->specialtyLinks->isNotEmpty() || $professional->serviceLinks->isNotEmpty()))
            ->map(fn (Professional $professional) => $this->mapOperationalProfessional($professional));

        return $siteProfessionals->concat($operationalOnly)->values();
    }

    private function isLinkedProfessionalEligible(?Professional $professional, Organization $organization): bool
    {
        if ($professional === null) {
            return true;
        }

        return $professional->organization_id === $organization->id
            && $professional->deleted_at === null
            && $professional->status === RecordStatus::Active;
    }

    /** @return array<string, mixed> */
    private function mapSiteProfessional(SiteProfessional $siteProfessional): array
    {
        return [
            'id' => 'site-'.$siteProfessional->id,
            'professional_id' => $siteProfessional->professional_id,
            'name' => $siteProfessional->name,
            'role_title' => $siteProfessional->role_title,
            'specialty' => $siteProfessional->specialty,
            'professional_register' => $siteProfessional->professional_register,
            'bio' => $siteProfessional->bio,
            'photo_url' => $siteProfessional->photo_path ? Storage::disk('public')->url($siteProfessional->photo_path) : null,
            'facebook_url' => $siteProfessional->facebook_url,
            'instagram_url' => $siteProfessional->instagram_url,
            'linkedin_url' => $siteProfessional->linkedin_url,
            'order' => $siteProfessional->order,
        ];
    }

    /** @return array<string, mixed> */
    private function mapOperationalProfessional(Professional $professional): array
    {
        return [
            'id' => 'professional-'.$professional->id,
            'professional_id' => $professional->id,
            'name' => $professional->display_name,
            'role_title' => null,
            'specialty' => $professional->specialtyLinks->pluck('specialty.name')->filter()->implode(', ') ?: null,
            'professional_register' => null,
            'bio' => $professional->bio,
            'photo_url' => $professional->photo_path ? Storage::disk('public')->url($professional->photo_path) : null,
            'facebook_url' => null,
            'instagram_url' => null,
            'linkedin_url' => null,
            'order' => 999,
        ];
    }
}
