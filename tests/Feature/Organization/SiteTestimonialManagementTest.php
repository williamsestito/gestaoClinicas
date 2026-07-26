<?php

declare(strict_types=1);

use App\Models\SiteService;
use App\Models\SiteTestimonial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets the owner create a testimonial without a photo, since it is always optional', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/testimonials', [
        'author_name' => 'Juliana R.',
        'content' => 'Atendimento excelente.',
        'rating' => 5,
    ])->assertRedirect();

    $testimonial = SiteTestimonial::query()->where('author_name', 'Juliana R.')->firstOrFail();
    expect($testimonial->author_photo_path)->toBeNull();
});

it('links a testimonial to an existing service', function () {
    $user = actingOwnerWithActiveContext();
    $service = SiteService::factory()->create();

    $this->actingAs($user)->post('/settings/site/testimonials', [
        'author_name' => 'Marcos T.',
        'content' => 'Ótimo resultado.',
        'related_service_id' => $service->id,
    ])->assertRedirect();

    $testimonial = SiteTestimonial::query()->where('author_name', 'Marcos T.')->firstOrFail();
    expect($testimonial->related_service_id)->toBe($service->id);
});

it('rejects a testimonial photo format other than JPEG, PNG and WebP', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/testimonials', [
        'author_name' => 'Foto inválida',
        'content' => 'Conteúdo.',
        'photo' => UploadedFile::fake()->image('foto.gif'),
    ])->assertSessionHasErrors('photo');

    expect(SiteTestimonial::query()->where('author_name', 'Foto inválida')->exists())->toBeFalse();
});

it('removes the photo file from storage when a testimonial is deleted', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $testimonial = SiteTestimonial::factory()->create(['author_photo_path' => 'site-testimonials/foto.jpg']);
    Storage::disk('public')->put('site-testimonials/foto.jpg', 'fake-content');

    $this->actingAs($user)->delete("/settings/site/testimonials/{$testimonial->id}")->assertRedirect();

    Storage::disk('public')->assertMissing('site-testimonials/foto.jpg');
});

it('rejects a rating outside the 1-5 range', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/testimonials', [
        'author_name' => 'Nome',
        'content' => 'Conteúdo',
        'rating' => 6,
    ])->assertSessionHasErrors('rating');
});
