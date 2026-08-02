<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\ActivateProfessionalAction;
use App\Actions\Organization\CreateProfessionalAction;
use App\Actions\Organization\DeactivateProfessionalAction;
use App\Actions\Organization\DeleteProfessionalAction;
use App\Actions\Organization\DestroyProfessionalPhotoAction;
use App\Actions\Organization\LinkProfessionalUserAction;
use App\Actions\Organization\RestoreProfessionalAction;
use App\Actions\Organization\UnlinkProfessionalUserAction;
use App\Actions\Organization\UpdateProfessionalAction;
use App\Actions\Organization\UpdateProfessionalPhotoAction;
use App\Enums\LegalEntityType;
use App\Enums\OrganizationMembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\CreateProfessionalRequest;
use App\Http\Requests\Organization\LinkProfessionalUserRequest;
use App\Http\Requests\Organization\UpdateProfessionalPhotoRequest;
use App\Http\Requests\Organization\UpdateProfessionalRequest;
use App\Models\OrganizationMembership;
use App\Models\Professional;
use App\Support\Documents\Document;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ProfessionalController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('viewAny', [Professional::class, $tenant->organization()]);

        $professionals = $tenant->organization()
            ->professionals()
            ->withTrashed()
            ->with('user:id,name')
            ->orderBy('display_name')
            ->get()
            ->map(fn (Professional $professional) => [
                'id' => $professional->id,
                'display_name' => $professional->display_name,
                'email' => $professional->email,
                'phone' => self::maskPhone($professional->phone),
                'document' => self::maskDocument($professional->document),
                'photo_url' => $professional->photo_path ? Storage::disk('public')->url($professional->photo_path) : null,
                'status' => $professional->status->value,
                'linked_user_name' => $professional->user?->name,
                'deleted_at' => $professional->deleted_at,
                'updated_at' => $professional->updated_at,
            ]);

        return Inertia::render('settings/professionals/Index', [
            'professionals' => $professionals,
            'eligibleUsers' => $this->eligibleUsers($tenant),
        ]);
    }

    public function create(TenantContext $tenant): Response
    {
        $this->authorize('create', [Professional::class, $tenant->organization()]);

        return Inertia::render('settings/professionals/Create', [
            'eligibleUsers' => $this->eligibleUsers($tenant),
        ]);
    }

    public function store(CreateProfessionalRequest $request, CreateProfessionalAction $action, TenantContext $tenant): RedirectResponse
    {
        $action->handle($tenant->organization(), $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional cadastrado com sucesso.']);

        return to_route('settings.professionals.index');
    }

    public function edit(Professional $professional, TenantContext $tenant): Response
    {
        $this->authorize('view', $professional);

        $professional->load('user:id,name');

        return Inertia::render('settings/professionals/Edit', [
            'professional' => [
                'id' => $professional->id,
                'name' => $professional->name,
                'social_name' => $professional->social_name,
                'display_name' => $professional->display_name,
                'email' => $professional->email,
                'phone' => $professional->phone,
                'document' => self::maskDocument($professional->document),
                'birth_date' => $professional->birth_date?->format('Y-m-d'),
                'bio' => $professional->bio,
                'photo_url' => $professional->photo_path ? Storage::disk('public')->url($professional->photo_path) : null,
                'status' => $professional->status->value,
                'linked_user' => $professional->user ? ['id' => $professional->user->id, 'name' => $professional->user->name] : null,
            ],
            'eligibleUsers' => $this->eligibleUsers($tenant),
        ]);
    }

    public function update(UpdateProfessionalRequest $request, Professional $professional, UpdateProfessionalAction $action): RedirectResponse
    {
        $action->handle($professional, $request->attributesForAction());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional alterado com sucesso.']);

        return to_route('settings.professionals.index');
    }

    public function activate(Professional $professional, ActivateProfessionalAction $action): RedirectResponse
    {
        $this->authorize('activate', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional ativado com sucesso.']);

        return back();
    }

    public function deactivate(Professional $professional, DeactivateProfessionalAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional inativado com sucesso.']);

        return back();
    }

    public function linkUser(LinkProfessionalUserRequest $request, Professional $professional, LinkProfessionalUserAction $action): RedirectResponse
    {
        $action->handle($professional, (int) $request->validated('user_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário vinculado ao profissional com sucesso.']);

        return back();
    }

    public function unlinkUser(Professional $professional, UnlinkProfessionalUserAction $action): RedirectResponse
    {
        $this->authorize('linkUser', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vínculo com o usuário removido com sucesso.']);

        return back();
    }

    public function updatePhoto(UpdateProfessionalPhotoRequest $request, Professional $professional, UpdateProfessionalPhotoAction $action): RedirectResponse
    {
        $action->handle($professional, $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto atualizada com sucesso.']);

        return back();
    }

    public function destroyPhoto(Professional $professional, DestroyProfessionalPhotoAction $action): RedirectResponse
    {
        $this->authorize('update', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto removida com sucesso.']);

        return back();
    }

    public function destroy(Professional $professional, DeleteProfessionalAction $action): RedirectResponse
    {
        $this->authorize('delete', $professional);

        $action->handle($professional);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional excluído com sucesso. Seu histórico foi preservado.']);

        return back();
    }

    public function restore(string $professional, TenantContext $tenant, RestoreProfessionalAction $action): RedirectResponse
    {
        $entity = Professional::withTrashed()->findOrFail($professional);

        if (! $tenant->organization() || $entity->organization_id !== $tenant->organization()->id) {
            abort(404);
        }

        $this->authorize('restore', $entity);

        $action->handle($entity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional restaurado com sucesso.']);

        return back();
    }

    /** @return array<int, array{id: int, name: string, email: string}> */
    private function eligibleUsers(TenantContext $tenant): array
    {
        $organization = $tenant->organization();

        $alreadyLinkedUserIds = Professional::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active)
            ->whereNotIn('user_id', $alreadyLinkedUserIds)
            ->with('user:id,name,email')
            ->get()
            ->map(fn (OrganizationMembership $membership) => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
            ])
            ->values()
            ->all();
    }

    private static function maskDocument(?string $document): ?string
    {
        if ($document === null || $document === '') {
            return null;
        }

        try {
            return Document::fromType(LegalEntityType::Individual, $document)->masked();
        } catch (InvalidArgumentException) {
            return '****';
        }
    }

    private static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) <= 4) {
            return str_repeat('•', strlen($digits));
        }

        return str_repeat('•', strlen($digits) - 4).substr($digits, -4);
    }
}
