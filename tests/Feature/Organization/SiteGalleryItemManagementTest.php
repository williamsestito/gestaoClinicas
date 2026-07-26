<?php

declare(strict_types=1);

use App\Actions\Site\SiteCollectionItemAction;
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

it('rejects gallery image formats other than JPEG, PNG and WebP', function (string $file) {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/gallery', [
        'caption' => 'Inválida',
        'image' => UploadedFile::fake()->image($file),
    ])->assertSessionHasErrors('image');

    expect(SiteGalleryItem::query()->where('caption', 'Inválida')->exists())->toBeFalse();
})->with(['imagem.gif', 'imagem.bmp']);

it('rejects a gallery image above the size limit', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/gallery', [
        'caption' => 'Grande demais',
        'image' => UploadedFile::fake()->image('grande.jpg')->size(5000),
    ])->assertSessionHasErrors('image');
});

it('rejects a malformed file disguised as a gallery image', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/gallery', [
        'caption' => 'Malformada',
        'image' => UploadedFile::fake()->create('imagem.jpg', 100, 'image/jpeg'),
    ])->assertSessionHasErrors('image');
});

it('replaces the previous gallery image and removes the old file only after success', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $item = SiteGalleryItem::factory()->create();
    $originalPath = $item->image_path;
    Storage::disk('public')->put($originalPath, 'fake-original-content');

    $this->actingAs($user)->put("/settings/site/gallery/{$item->id}", [
        'caption' => $item->caption,
        'image' => UploadedFile::fake()->image('nova.jpg'),
    ])->assertSessionHasNoErrors();

    $newPath = $item->fresh()->image_path;

    expect($newPath)->not->toBe($originalPath);
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing($originalPath);
});

it('keeps the previous gallery image untouched when persisting the new one fails', function () {
    Storage::fake('public');
    $item = SiteGalleryItem::factory()->create();
    $originalPath = $item->image_path;
    Storage::disk('public')->put($originalPath, 'fake-original-content');

    SiteGalleryItem::saving(function () {
        throw new RuntimeException('Simulated database failure.');
    });

    try {
        $action = app(SiteCollectionItemAction::class);
        $action->replaceImage($item, 'image_path', UploadedFile::fake()->image('nova.jpg'), 'site-gallery');

        $this->expectException(RuntimeException::class);
        $action->upsert($item, ['caption' => $item->caption]);
    } finally {
        SiteGalleryItem::unsetEventDispatcher();
    }

    expect($item->fresh()->image_path)->toBe($originalPath);
    Storage::disk('public')->assertExists($originalPath);
});

it('removes the image file from storage when a gallery item is deleted', function () {
    Storage::fake('public');
    $user = actingOwnerWithActiveContext();
    $item = SiteGalleryItem::factory()->create();
    $path = $item->image_path;
    Storage::disk('public')->put($path, 'fake-original-content');

    $this->actingAs($user)->delete("/settings/site/gallery/{$item->id}")->assertRedirect();

    Storage::disk('public')->assertMissing($path);
});
