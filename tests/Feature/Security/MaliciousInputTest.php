<?php

declare(strict_types=1);

use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SiteFaq;
use App\Models\SiteSetting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('confines a malicious FAQ payload to the inert Inertia JSON data island, never to executable markup', function () {
    Organization::factory()->create();
    SiteSetting::factory()->create();

    $scriptPayload = '<script>alert(1)</script>';
    $imgPayload = '<img src=x onerror=alert(1)>';
    SiteFaq::factory()->create([
        'question' => $scriptPayload,
        'answer' => $imgPayload,
        'is_active' => true,
    ]);

    $response = $this->get('/');
    $response->assertOk();

    // A página inteira é um único <script type="application/json"> com os
    // dados da Inertia — o navegador nunca interpreta esse conteúdo como
    // HTML/DOM. O payload malicioso só é seguro se estiver confinado a essa
    // ilha JSON; fora dela, apareceria como markup real e executável.
    $html = $response->getContent();
    preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $matches);
    $jsonIsland = $matches[1] ?? '';
    $htmlOutsideIsland = str_replace($jsonIsland, '', $html);

    expect($jsonIsland)->toContain('question')
        ->and($htmlOutsideIsland)->not->toContain($scriptPayload)
        ->and($htmlOutsideIsland)->not->toContain($imgPayload);
});

it('ignores organization_id and is_owner sent by the client when updating a membership', function () {
    $owner = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));
    $otherOrganization = Organization::factory()->create();
    $member = User::factory()->create();
    $membership = OrganizationMembership::factory()->for($organization)->for($member)->create();

    $this->actingAs($owner)
        ->put(route('settings.users.update', $membership), [
            'admin_note' => 'nota legítima',
            'organization_id' => $otherOrganization->id,
            'is_owner' => true,
        ])
        ->assertSessionHasNoErrors();

    $membership->refresh();
    expect($membership->organization_id)->toBe($organization->id)
        ->and($membership->is_owner)->toBeFalse();
});

it('rejects a PHP file disguised as an image on every institutional upload field', function () {
    $user = actingOwnerWithActiveContext();
    $organization = Organization::find(session('active_organization_id'));

    $fakeImage = UploadedFile::fake()->createWithContent('shell.png', "<?php echo 'pwned'; ?>");

    $this->actingAs($user)
        ->put('/settings/site', [
            'business_name' => $organization->name,
            'hero_image' => $fakeImage,
        ])
        ->assertSessionHasErrors('hero_image');
});

it('rate limits the active organization/unit context switch', function () {
    $organization = Organization::factory()->create();
    $legalEntity = LegalEntity::factory()->primary()->for($organization)->create();
    Unit::factory()->headquarters()->for($organization)->for($legalEntity, 'legalEntity')->create();
    $user = User::factory()->create();
    OrganizationMembership::factory()->owner()->for($organization)->for($user)->create();

    for ($i = 0; $i < 20; $i++) {
        $this->actingAs($user)->put('/context/organization', ['organization_id' => $organization->id]);
    }

    $this->actingAs($user)
        ->put('/context/organization', ['organization_id' => $organization->id])
        ->assertStatus(429);
});
