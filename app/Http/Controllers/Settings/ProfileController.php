<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Account\RequestAccountClosureAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Settings\UpdateProfilePhotoRequest;
use App\Support\Documents\BrazilianState;
use App\Support\Site\SafeFileReplacer;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'states' => BrazilianState::codes(),
            'profile' => [
                'phone' => $user->phone,
                'cpf' => $user->cpf,
                'photo_url' => $user->photo_path ? Storage::disk('public')->url($user->photo_path) : null,
                'address' => [
                    'postal_code' => $user->address_postal_code ?? '',
                    'street' => $user->address_street ?? '',
                    'number' => $user->address_number ?? '',
                    'complement' => $user->address_complement ?? '',
                    'neighborhood' => $user->address_neighborhood ?? '',
                    'city' => $user->address_city ?? '',
                    'state' => $user->address_state ?? '',
                ],
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // O CPF já chega normalizado (dígitos apenas) — ver
        // ProfileUpdateRequest::prepareForValidation().
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Envia ou substitui a foto do próprio perfil. Reaproveita o mesmo
     * padrão seguro de substituição de arquivo já usado para banner/logo/
     * favicon do site (App\Support\Site\SafeFileReplacer é genérico —
     * funciona com qualquer Model+coluna, não só SiteSetting).
     */
    public function updatePhoto(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();
        $replacer = new SafeFileReplacer;
        $replacer->stage($user, 'photo_path', $request->file('photo'), 'profile-photos');

        try {
            $user->save();
        } catch (Throwable $e) {
            $replacer->rollback();

            throw $e;
        }

        $replacer->commit();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto atualizada com sucesso.']);

        return to_route('profile.edit');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->photo_path && Storage::disk('public')->exists($user->photo_path)) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $user->update(['photo_path' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto removida.']);

        return to_route('profile.edit');
    }

    /**
     * Encerra o acesso do usuário à plataforma (desativação, nunca exclusão
     * física — ver RequestAccountClosureAction).
     */
    public function destroy(ProfileDeleteRequest $request, RequestAccountClosureAction $action): RedirectResponse
    {
        $user = $request->user();

        $action->handle($user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Sua conta foi desativada. Se precisar reativá-la, entre em contato com o suporte.');
    }
}
