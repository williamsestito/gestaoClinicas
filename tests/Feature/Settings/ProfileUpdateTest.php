<?php

namespace Tests\Feature\Settings;

use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_information_including_phone_cpf_and_address_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'phone' => '(47) 99999-1234',
                'cpf' => '390.533.447-05',
                'address_postal_code' => '01310-100',
                'address_street' => 'Av. Paulista',
                'address_number' => '1000',
                'address_complement' => 'Sala 1',
                'address_neighborhood' => 'Bela Vista',
                'address_city' => 'São Paulo',
                'address_state' => 'SP',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('(47) 99999-1234', $user->phone);
        $this->assertSame('39053344705', $user->cpf, 'CPF deve ser normalizado para apenas dígitos.');
        $this->assertSame('01310100', $user->address_postal_code, 'CEP deve ser normalizado para apenas dígitos.');
        $this->assertSame('Av. Paulista', $user->address_street);
        $this->assertSame('1000', $user->address_number);
        $this->assertSame('Sala 1', $user->address_complement);
        $this->assertSame('Bela Vista', $user->address_neighborhood);
        $this->assertSame('São Paulo', $user->address_city);
        $this->assertSame('SP', $user->address_state);
    }

    public function test_rejects_an_invalid_cpf()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'cpf' => '111.111.111-11',
            ]);

        $response->assertSessionHasErrors('cpf');
        $this->assertNull($user->fresh()->cpf);
    }

    public function test_rejects_a_cpf_already_used_by_another_user()
    {
        $existing = User::factory()->create(['cpf' => '39053344705']);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'cpf' => '390.533.447-05',
            ]);

        $response->assertSessionHasErrors('cpf');
        $this->assertNull($user->fresh()->cpf);
        $this->assertNotNull($existing->fresh()->cpf);
    }

    public function test_user_can_upload_and_remove_a_profile_photo()
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.photo.update'), [
                'photo' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNotNull($user->photo_path);
        Storage::disk('public')->assertExists($user->photo_path);

        $photoPath = $user->photo_path;

        $this->actingAs($user)
            ->delete(route('profile.photo.destroy'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->fresh()->photo_path);
        Storage::disk('public')->assertMissing($photoPath);
    }

    public function test_rejects_a_php_file_disguised_as_a_profile_photo()
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.photo.update'), [
                'photo' => UploadedFile::fake()->createWithContent('shell.jpg', "<?php echo 'pwned'; ?>"),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->photo_path);
    }

    public function test_user_can_request_account_closure()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh, 'Encerrar a conta nunca deve excluir o usuário fisicamente.');
        $this->assertFalse($fresh->is_active);
    }

    public function test_sole_active_owner_cannot_request_account_closure()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->memberships()->create([
            'user_id' => $user->id,
            'status' => OrganizationMembershipStatus::Active,
            'is_owner' => true,
            'joined_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
