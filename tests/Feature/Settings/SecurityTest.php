<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('canManagePasskeys', true)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', false),
            );
    }

    public function test_security_page_requires_password_confirmation_when_enabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('canManagePasskeys', false)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_two_factor_secret_and_recovery_codes_are_never_exposed_in_the_security_page()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);

        $secret = 'JBSWY3DPEHPK3PXP';
        $recoveryCodes = ['fake-recovery-code-1', 'fake-recovery-code-2'];

        $user = User::factory()->create([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('twoFactorEnabled', true)
                ->missing('twoFactorSecret')
                ->missing('recoveryCodes'),
            );

        $body = $response->getContent();
        $this->assertStringNotContainsString($secret, (string) $body);
        foreach ($recoveryCodes as $code) {
            $this->assertStringNotContainsString($code, (string) $body);
        }
    }

    public function test_passkey_list_never_exposes_the_raw_credential()
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        Features::passkeys(['confirmPassword' => true]);

        $user = User::factory()->create();
        $secretCredentialMarker = 'secret-public-key-marker';
        $user->passkeys()->create([
            'name' => 'Meu notebook',
            'credential_id' => 'credential-id-'.$user->id,
            'credential' => ['publicKey' => $secretCredentialMarker],
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->has('passkeys', 1)
                ->where('passkeys.0.name', 'Meu notebook')
            );

        $this->assertStringNotContainsString($secretCredentialMarker, (string) $response->getContent());
    }

    public function test_a_user_cannot_delete_another_users_passkey()
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        Features::passkeys(['confirmPassword' => true]);

        $owner = User::factory()->create();
        $passkey = $owner->passkeys()->create([
            'name' => 'Passkey de outra pessoa',
            'credential_id' => 'credential-id-owner',
            'credential' => ['publicKey' => 'x'],
        ]);

        $attacker = User::factory()->create();

        $this->actingAs($attacker)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('passkey.destroy', $passkey))
            ->assertForbidden();

        $this->assertNotNull(Passkey::find($passkey->id));
    }

    public function test_a_user_can_delete_their_own_passkey()
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        Features::passkeys(['confirmPassword' => true]);

        $user = User::factory()->create();
        $passkey = $user->passkeys()->create([
            'name' => 'Meu celular',
            'credential_id' => 'credential-id-'.$user->id,
            'credential' => ['publicKey' => 'x'],
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('passkey.destroy', $passkey))
            ->assertRedirect();

        $this->assertNull(Passkey::find($passkey->id));
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }
}
