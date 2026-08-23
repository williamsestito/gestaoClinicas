<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a photo to the private disk, never to the public disk', function () {
    Storage::fake('local');
    Storage::fake('public');
    $setup = patientPortalProfileSetup();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $this->actingAs($setup['patientUser'], 'patient')
        ->post("/portal/pacientes/{$setup['patient']->id}/foto", ['photo' => $file])
        ->assertRedirect();

    $path = $setup['patient']->fresh()->photo_path;
    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

it('serves the photo only to the account managing that patient', function () {
    Storage::fake('local');
    $setup = patientPortalProfileSetup();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);
    $this->actingAs($setup['patientUser'], 'patient')
        ->post("/portal/pacientes/{$setup['patient']->id}/foto", ['photo' => $file]);

    $this->actingAs($setup['patientUser'], 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/foto")
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

it('returns 404 when a patient account tries to fetch another patient\'s photo', function () {
    Storage::fake('local');
    $setup = patientPortalProfileSetup();
    $otherOrganization = Organization::factory()->create();
    $foreignPatient = Patient::factory()->for($otherOrganization)->create(['photo_path' => 'patient-photos/fake.jpg']);
    Storage::disk('local')->put('patient-photos/fake.jpg', 'fake-image-content');

    $this->actingAs($setup['patientUser'], 'patient')
        ->get("/portal/pacientes/{$foreignPatient->id}/foto")
        ->assertNotFound();
});

it('redirects an unauthenticated request to the patient portal login', function () {
    Storage::fake('local');
    $organization = Organization::factory()->create();
    $patient = Patient::factory()->for($organization)->create(['photo_path' => 'patient-photos/fake.jpg']);
    Storage::disk('local')->put('patient-photos/fake.jpg', 'fake-image-content');

    $this->get("/portal/pacientes/{$patient->id}/foto")->assertRedirect('/login');
});

it('removes a photo from the private disk', function () {
    Storage::fake('local');
    $setup = patientPortalProfileSetup();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);
    $this->actingAs($setup['patientUser'], 'patient')
        ->post("/portal/pacientes/{$setup['patient']->id}/foto", ['photo' => $file]);
    $path = $setup['patient']->fresh()->photo_path;

    $this->actingAs($setup['patientUser'], 'patient')
        ->delete("/portal/pacientes/{$setup['patient']->id}/foto")
        ->assertRedirect();

    expect($setup['patient']->fresh()->photo_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('includes the photo_url in the edit page once a photo exists', function () {
    Storage::fake('local');
    $setup = patientPortalProfileSetup();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);
    $this->actingAs($setup['patientUser'], 'patient')
        ->post("/portal/pacientes/{$setup['patient']->id}/foto", ['photo' => $file]);

    $this->actingAs($setup['patientUser'], 'patient')
        ->get("/portal/pacientes/{$setup['patient']->id}/editar")
        ->assertInertia(fn ($page) => $page->where(
            'patient.photo_url',
            route('patient-portal.patients.photo.show', $setup['patient']),
        ));
});

it('rejects a non-image file', function () {
    Storage::fake('local');
    $setup = patientPortalProfileSetup();
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $this->actingAs($setup['patientUser'], 'patient')
        ->post("/portal/pacientes/{$setup['patient']->id}/foto", ['photo' => $file])
        ->assertSessionHasErrors('photo');
});
