<?php

declare(strict_types=1);

use App\Models\SiteGalleryItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('requires an image when creating a gallery item', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/gallery', [
        'caption' => 'Sem imagem',
    ])->assertSessionHasErrors('image');
});

it('lets the owner create a gallery item with an image', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/gallery', [
        'caption' => 'Recepção',
        'image' => UploadedFile::fake()->image('recepcao.jpg'),
    ])->assertRedirect();

    $item = SiteGalleryItem::query()->where('caption', 'Recepção')->firstOrFail();
    Storage::disk('public')->assertExists($item->image_path);
});

it('does not require a new image when updating an existing gallery item', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $item = SiteGalleryItem::factory()->create();

    $this->actingAs($user)->put("/settings/site/gallery/{$item->id}", [
        'caption' => 'Legenda atualizada',
    ])->assertSessionHasNoErrors();

    expect($item->fresh()->caption)->toBe('Legenda atualizada');
});
