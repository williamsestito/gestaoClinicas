<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a patient photo to the private disk, never to the public disk', function () {
    Storage::fake('local');
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $this->actingAs($user)->post("/settings/patients/{$patient->id}/photo", [
        'photo' => $file,
    ])->assertRedirect();

    $path = $patient->fresh()->photo_path;
    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

it('serves the patient photo only to an authorized user of the same organization', function () {
    Storage::fake('local');
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);
    $this->actingAs($user)->post("/settings/patients/{$patient->id}/photo", ['photo' => $file]);

    $this->actingAs($user)->get("/settings/patients/{$patient->id}/photo")
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

it('blocks fetching a patient photo of another organization even with a valid id', function () {
    Storage::fake('local');
    $owner = actingOwnerWithActiveContext();
    $otherOrganization = Organization::factory()->create();
    $foreignPatient = Patient::factory()->for($otherOrganization)->create(['photo_path' => 'patient-photos/fake.jpg']);
    Storage::disk('local')->put('patient-photos/fake.jpg', 'fake-image-content');

    $this->actingAs($owner)->get("/settings/patients/{$foreignPatient->id}/photo")
        ->assertNotFound();
});

it('redirects an unauthenticated request to the patient photo endpoint to login', function () {
    Storage::fake('local');
    $organization = Organization::factory()->create();
    $patient = Patient::factory()->for($organization)->create(['photo_path' => 'patient-photos/fake.jpg']);
    Storage::disk('local')->put('patient-photos/fake.jpg', 'fake-image-content');

    $this->get("/settings/patients/{$patient->id}/photo")->assertRedirect('/login');
});

it('removes a patient photo from the private disk', function () {
    Storage::fake('local');
    $user = actingOwnerWithActiveContext();
    $patient = Patient::factory()->for($user->organizationMemberships()->first()->organization)->create();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);
    $this->actingAs($user)->post("/settings/patients/{$patient->id}/photo", ['photo' => $file]);
    $path = $patient->fresh()->photo_path;

    $this->actingAs($user)->delete("/settings/patients/{$patient->id}/photo")->assertRedirect();

    expect($patient->fresh()->photo_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});
