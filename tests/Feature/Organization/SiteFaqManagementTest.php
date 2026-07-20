<?php

declare(strict_types=1);

use App\Models\SiteFaq;

it('lets the owner create, reorder and delete an faq entry', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/faq', [
        'question' => 'Como agendar?',
        'answer' => 'Pelo formulário desta página.',
    ])->assertRedirect();

    $faq = SiteFaq::query()->where('question', 'Como agendar?')->firstOrFail();

    $this->actingAs($user)->delete("/settings/site/faq/{$faq->id}")->assertRedirect();

    expect(SiteFaq::query()->find($faq->id))->toBeNull();
});

it('validates required fields', function () {
    $user = actingOwnerWithActiveContext();

    $this->actingAs($user)->post('/settings/site/faq', [])
        ->assertSessionHasErrors(['question', 'answer']);
});
